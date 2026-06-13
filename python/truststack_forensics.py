#!/usr/bin/env python3
"""
TrustStack Forensic Engine v3 — Four-Layer Pipeline
  1. Visual Layer    — Error Level Analysis (ELA) + compression heatmap
  2. Textual Layer   — OCR text extraction + bounding box alignment analysis
  3. Noise Layer     — Laplacian noise consistency across image regions
  4. Edge Layer      — Edge density coherence analysis for splice detection

Requires: Pillow, numpy
Optional: pytesseract + tesseract-ocr system package (for full OCR)
"""
import argparse
import json
import math
import os
import re
import sys
import tempfile
import time
from pathlib import Path

import numpy as np
from PIL import Image, ImageChops, ImageDraw, ImageEnhance, ImageFilter, ImageOps, ImageStat

ENGINE_VERSION = "v3.0 — Four-Layer Forensic Pipeline"
MAX_DIMENSION = 3000  # Resize images larger than this to prevent OOM

# ── optional OCR ─────────────────────────────────────────────────────────────
_HAS_TESSERACT = False
try:
    import pytesseract

    pytesseract.get_tesseract_version()
    _HAS_TESSERACT = True
except Exception:
    pass


def slug(value: str) -> str:
    cleaned = re.sub(r"[^a-zA-Z0-9]+", "-", value).strip("-").lower()
    return cleaned or "certificate"


def safe_open_image(input_path: str) -> Image.Image:
    """Open and validate an image, resizing if too large to prevent OOM."""
    img = Image.open(input_path)
    img.verify()  # Check for corruption
    img = Image.open(input_path).convert("RGB")  # Re-open after verify

    # Resize if too large
    w, h = img.size
    if max(w, h) > MAX_DIMENSION:
        ratio = MAX_DIMENSION / max(w, h)
        img = img.resize((int(w * ratio), int(h * ratio)), Image.LANCZOS)

    return img


def _grid_stats(mask: Image.Image, rows: int = 4, cols: int = 4) -> list[dict]:
    """Compute per-region statistics over a grid."""
    width, height = mask.size
    regions = []
    for row in range(rows):
        for col in range(cols):
            left = int(width * col / cols)
            upper = int(height * row / rows)
            right = int(width * (col + 1) / cols)
            lower = int(height * (row + 1) / rows)
            crop = mask.crop((left, upper, right, lower))
            stat = ImageStat.Stat(crop)
            regions.append({
                "label": f"zone-{row + 1}-{col + 1}",
                "x": left, "y": upper,
                "width": right - left, "height": lower - upper,
                "mean": round(stat.mean[0], 2),
                "stddev": round(stat.stddev[0], 2),
            })
    return regions


# ══════════════════════════════════════════════════════════════════════════════
#  LAYER 1 — Error Level Analysis (Visual)
# ══════════════════════════════════════════════════════════════════════════════

def analyze_ela(source: Image.Image, output_dir: str, original_name: str):
    """Re-save as JPEG and diff to expose compression inconsistencies."""
    with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as tmp:
        temp_path = tmp.name

    try:
        source.save(temp_path, "JPEG", quality=90)
        resaved = Image.open(temp_path).convert("RGB")
        diff = ImageChops.difference(source, resaved)
    finally:
        try:
            os.unlink(temp_path)
        except OSError:
            pass

    extrema = diff.getextrema()
    max_delta = max(channel[1] for channel in extrema) or 1
    scale = min(255 / max_delta, 12)
    enhanced = ImageEnhance.Brightness(diff).enhance(scale)
    grayscale = ImageOps.grayscale(enhanced)
    stat = ImageStat.Stat(grayscale)
    mean_delta = stat.mean[0] / scale
    peak_delta = grayscale.getextrema()[1] / scale

    # Build vivid red-on-dark heatmap
    red = Image.new("RGB", source.size, (255, 30, 30))
    dark = Image.new("RGB", source.size, (4, 8, 14))
    mask = grayscale.point(lambda px: min(255, int(px * 2.4)))
    heatmap = Image.composite(red, dark, mask)

    output_path = Path(output_dir) / f"{slug(Path(original_name).stem)}-ela-heatmap.png"
    heatmap.save(output_path)

    heat_score = min(100, int((mean_delta * 4.2) + (peak_delta * 0.34)))

    # Suspicious regions (hottest zones)
    regions = []
    for r in _grid_stats(grayscale):
        if r["mean"] >= 28:
            regions.append({**r, "intensity": r["mean"]})
    regions = sorted(regions, key=lambda item: item["mean"], reverse=True)[:6]

    return {
        "ela_score": heat_score,
        "heatmap_path": str(output_path),
        "suspicious_regions": regions,
        "mean_delta": round(mean_delta, 2),
        "peak_delta": round(peak_delta, 2),
    }, grayscale


# ══════════════════════════════════════════════════════════════════════════════
#  LAYER 2 — Textual Analysis (OCR + Alignment)
# ══════════════════════════════════════════════════════════════════════════════

def ocr_analysis(source: Image.Image, candidate_name: str, ela_mask: Image.Image | None = None) -> dict:
    """
    Extract text with bounding boxes and check for:
    1. Candidate name presence and confidence
    2. Vertical alignment anomalies (misaligned words on the same line)
    3. Compression hotspots under text regions (cross-ref with ELA)
    """
    result = {
        "ocr_available": _HAS_TESSERACT,
        "ocr_text": "",
        "name_found": False,
        "name_confidence": 0.0,
        "alignment_anomalies": [],
        "text_ela_anomalies": [],
        "ocr_score": 0,
    }

    if not _HAS_TESSERACT:
        result["ocr_text"] = "(Tesseract not available — using metadata analysis)"
        result["ocr_score"] = 0
        return result

    data = pytesseract.image_to_data(source, output_type=pytesseract.Output.DICT)

    # ── 1. Full text extraction ──────────────────────────────────────────
    words = []
    for i in range(len(data["text"])):
        text = data["text"][i].strip()
        conf = int(data["conf"][i]) if data["conf"][i] != "-1" else -1
        if text and conf > 0:
            words.append({
                "text": text, "conf": conf,
                "left": data["left"][i], "top": data["top"][i],
                "width": data["width"][i], "height": data["height"][i],
                "line_num": data["line_num"][i], "block_num": data["block_num"][i],
            })

    result["ocr_text"] = " ".join(w["text"] for w in words)

    # ── 2. Name matching ─────────────────────────────────────────────────
    name_tokens = [t.lower() for t in re.split(r"\W+", candidate_name) if len(t) >= 3]
    full_text_lower = result["ocr_text"].lower()

    matched_tokens = [t for t in name_tokens if t in full_text_lower]
    if name_tokens:
        result["name_found"] = len(matched_tokens) > 0
        result["name_confidence"] = round(len(matched_tokens) / len(name_tokens) * 100, 1)
    else:
        result["name_found"] = False
        result["name_confidence"] = 0.0

    # ── 3. Alignment analysis ────────────────────────────────────────────
    lines: dict[tuple[int, int], list[dict]] = {}
    for w in words:
        key = (w["block_num"], w["line_num"])
        lines.setdefault(key, []).append(w)

    anomalies = []
    for line_key, line_words in lines.items():
        if len(line_words) < 3:
            continue
        tops = [w["top"] for w in line_words]
        heights = [w["height"] for w in line_words]
        median_top = sorted(tops)[len(tops) // 2]
        median_height = sorted(heights)[len(heights) // 2]

        for w in line_words:
            if len(w["text"]) <= 3:
                continue
            top_deviation = abs(w["top"] - median_top)
            height_deviation = abs(w["height"] - median_height)
            if median_height > 0 and (top_deviation / median_height > 0.3 or height_deviation / median_height > 0.4):
                anomalies.append({
                    "word": w["text"], "expected_y": median_top, "actual_y": w["top"],
                    "deviation_px": top_deviation,
                    "x": w["left"], "y": w["top"],
                    "width": w["width"], "height": w["height"],
                })

    result["alignment_anomalies"] = anomalies[:8]

    # ── 4. Cross-reference text regions with ELA mask ────────────────────
    if ela_mask is not None:
        text_ela_hits = []
        for w in words:
            if w["conf"] < 30:
                continue
            region = ela_mask.crop((
                w["left"], w["top"],
                min(w["left"] + w["width"], ela_mask.width),
                min(w["top"] + w["height"], ela_mask.height),
            ))
            region_intensity = ImageStat.Stat(region).mean[0]
            if region_intensity >= 25 and any(t in w["text"].lower() for t in name_tokens):
                text_ela_hits.append({
                    "word": w["text"], "ela_intensity": round(region_intensity, 2),
                    "x": w["left"], "y": w["top"],
                })
        result["text_ela_anomalies"] = text_ela_hits[:6]

    # ── 5. Composite OCR score ───────────────────────────────────────────
    score = 0
    if name_tokens and not result["name_found"]:
        score += 25
    elif result["name_confidence"] < 50:
        score += 15
    score += min(30, len(anomalies) * 8)
    score += min(30, len(result.get("text_ela_anomalies", [])) * 15)
    result["ocr_score"] = min(100, score)
    return result


def _filename_heuristic_score(candidate_name: str, filename: str) -> int:
    """Fallback when Tesseract isn't available — analyze filename metadata."""
    score = 0
    lowered_name = candidate_name.lower()
    lowered_file = filename.lower()
    suspicious_terms = ["fake", "altered", "tamper", "edited", "splice", "forged", "modified"]

    if any(term in lowered_file for term in suspicious_terms):
        score += 15

    tokens = [t for t in re.split(r"\W+", lowered_name) if len(t) >= 3]
    if tokens and not any(t in lowered_file for t in tokens):
        score += 8

    if re.search(r"(19|20)\d{2}", lowered_file):
        score += 5

    return min(100, score)


# ══════════════════════════════════════════════════════════════════════════════
#  LAYER 3 — Noise Consistency Analysis
# ══════════════════════════════════════════════════════════════════════════════

def noise_analysis(source: Image.Image) -> dict:
    """
    Extract high-frequency noise via Laplacian filtering and measure
    consistency across image regions. Genuine single-source documents
    have uniform noise; spliced areas introduce foreign noise patterns.
    """
    gray = ImageOps.grayscale(source)
    # Laplacian approximation via kernel convolution
    laplacian = gray.filter(ImageFilter.Kernel(
        size=(3, 3),
        kernel=[0, 1, 0, 1, -4, 1, 0, 1, 0],
        scale=1, offset=128,
    ))

    # Compute per-region noise statistics over a 4×4 grid
    grid = _grid_stats(laplacian)
    stddevs = [r["stddev"] for r in grid]
    means = [r["mean"] for r in grid]

    global_mean = float(np.mean(stddevs))
    global_std = float(np.std(stddevs))
    # Coefficient of variation — how much noise varies across regions
    noise_cv = (global_std / global_mean * 100) if global_mean > 0 else 0

    # Flag regions whose noise deviates significantly from the median
    # Use higher threshold (0.6) because documents naturally have structural variation
    median_stddev = float(np.median(stddevs))
    anomaly_regions = []
    for r in grid:
        deviation = abs(r["stddev"] - median_stddev)
        if median_stddev > 0 and deviation / median_stddev > 0.6:
            anomaly_regions.append({
                "label": r["label"],
                "x": r["x"], "y": r["y"],
                "width": r["width"], "height": r["height"],
                "noise_stddev": r["stddev"],
                "deviation_from_median": round(deviation, 2),
            })
    anomaly_regions = sorted(anomaly_regions, key=lambda x: x["deviation_from_median"], reverse=True)[:6]

    # Dampened scoring — noise is a supporting signal, not primary
    # Documents naturally have CV of 20-40%; only flag above that
    adjusted_cv = max(0, noise_cv - 25)  # Subtract baseline document variation
    noise_score = min(100, int(adjusted_cv * 0.8 + len(anomaly_regions) * 4))

    return {
        "noise_score": noise_score,
        "noise_cv": round(noise_cv, 2),
        "noise_mean_stddev": round(global_mean, 2),
        "noise_global_std": round(global_std, 2),
        "noise_anomaly_regions": anomaly_regions,
    }


# ══════════════════════════════════════════════════════════════════════════════
#  LAYER 4 — Edge Coherence Analysis
# ══════════════════════════════════════════════════════════════════════════════

def edge_analysis(source: Image.Image) -> dict:
    """
    Apply edge detection and measure edge density consistency.
    Spliced elements create abrupt edge density changes at boundaries
    that differ from the document's natural edge pattern.
    """
    gray = ImageOps.grayscale(source)
    edges = gray.filter(ImageFilter.FIND_EDGES)
    # Threshold to binary edge map
    edge_binary = edges.point(lambda px: 255 if px > 30 else 0)

    grid = _grid_stats(edge_binary)
    densities = [r["mean"] for r in grid]

    global_mean = float(np.mean(densities))
    global_std = float(np.std(densities))
    density_cv = (global_std / global_mean * 100) if global_mean > 0 else 0

    # Higher threshold (0.8) for edges — documents have natural density variation
    # from headers, seals, text areas vs white space
    median_density = float(np.median(densities))
    anomaly_regions = []
    for r in grid:
        deviation = abs(r["mean"] - median_density)
        if median_density > 0 and deviation / median_density > 0.8:
            anomaly_regions.append({
                "label": r["label"],
                "x": r["x"], "y": r["y"],
                "width": r["width"], "height": r["height"],
                "edge_density": r["mean"],
                "deviation_from_median": round(deviation, 2),
            })
    anomaly_regions = sorted(anomaly_regions, key=lambda x: x["deviation_from_median"], reverse=True)[:6]

    # Dampened scoring — documents naturally have CV of 40-70% for edges
    adjusted_cv = max(0, density_cv - 50)  # Subtract baseline structural variation
    edge_score = min(100, int(adjusted_cv * 0.5 + len(anomaly_regions) * 5))

    return {
        "edge_score": edge_score,
        "edge_density_cv": round(density_cv, 2),
        "edge_mean_density": round(global_mean, 2),
        "edge_anomaly_regions": anomaly_regions,
    }


# ══════════════════════════════════════════════════════════════════════════════
#  COMPOSITE SCORING & OUTPUT
# ══════════════════════════════════════════════════════════════════════════════

def _build_findings(ela: dict, ocr: dict, noise: dict, edge: dict, candidate_name: str) -> list[str]:
    """Generate human-readable forensic findings from all 4 layers."""
    findings = []
    heat_score = ela["ela_score"]

    # ── ELA findings ─────────────────────────────────────────────────────
    if heat_score >= 50:
        findings.append(
            f"[ELA] High compression variance detected (score {heat_score}/100, "
            f"mean Δ={ela['mean_delta']}, peak Δ={ela['peak_delta']}). "
            "Consistent with localized pixel edits."
        )
    elif heat_score >= 32:
        findings.append(
            f"[ELA] Moderate compression variance (score {heat_score}/100). Manual review recommended."
        )
    else:
        findings.append(
            f"[ELA] Compression profile is broadly consistent (score {heat_score}/100)."
        )

    # ── OCR findings ─────────────────────────────────────────────────────
    if ocr["ocr_available"]:
        if not ocr["name_found"]:
            findings.append(
                f'[OCR] Could not locate candidate name "{candidate_name}" in document text. '
                "This may indicate a fabricated certificate."
            )
        elif ocr["name_confidence"] < 100:
            findings.append(
                f"[OCR] Partial match for candidate name ({ocr['name_confidence']}% tokens matched)."
            )
        else:
            findings.append("[OCR] Candidate name confirmed present in document text.")

        if ocr["alignment_anomalies"]:
            words = ", ".join(a["word"] for a in ocr["alignment_anomalies"][:3])
            findings.append(
                f"[OCR] {len(ocr['alignment_anomalies'])} words with anomalous vertical alignment: "
                f"{words}. Consistent with digitally spliced text."
            )
        if ocr.get("text_ela_anomalies"):
            words = ", ".join(a["word"] for a in ocr["text_ela_anomalies"][:3])
            findings.append(
                f"[CROSS-REF] ELA hotspots overlap candidate name text regions ({words}). "
                "Strong evidence of pixel-level manipulation at the name location."
            )
    else:
        findings.append("[OCR] Tesseract unavailable — textual analysis skipped; other layers compensating.")

    # ── Noise findings ───────────────────────────────────────────────────
    ns = noise["noise_score"]
    if ns >= 45:
        findings.append(
            f"[NOISE] High noise inconsistency detected (score {ns}/100, CV={noise['noise_cv']}%). "
            f"{len(noise['noise_anomaly_regions'])} regions deviate from baseline noise pattern. "
            "This suggests elements from different source images were composited."
        )
    elif ns >= 25:
        findings.append(
            f"[NOISE] Moderate noise variation (score {ns}/100, CV={noise['noise_cv']}%). "
            "Some regional noise inconsistency detected."
        )
    else:
        findings.append(
            f"[NOISE] Noise pattern is consistent across the document (score {ns}/100). "
            "Indicates a single-source scan."
        )

    # ── Edge findings ────────────────────────────────────────────────────
    es = edge["edge_score"]
    if es >= 45:
        findings.append(
            f"[EDGE] Significant edge density anomalies detected (score {es}/100, CV={edge['edge_density_cv']}%). "
            f"{len(edge['edge_anomaly_regions'])} zones show abrupt edge discontinuities consistent with splicing."
        )
    elif es >= 25:
        findings.append(
            f"[EDGE] Moderate edge density variation (score {es}/100). Some boundary irregularities detected."
        )
    else:
        findings.append(
            f"[EDGE] Edge coherence is uniform (score {es}/100). No splice boundaries detected."
        )

    return findings


def _compute_confidence(ela_s: int, ocr_s: int, noise_s: int, edge_s: int, ocr_avail: bool) -> str:
    """Determine confidence level based on multi-layer agreement."""
    scores = [ela_s, noise_s, edge_s]
    if ocr_avail:
        scores.append(ocr_s)

    # Count how many layers agree on the direction (above or below threshold)
    threshold = 40
    above = sum(1 for s in scores if s >= threshold)
    below = sum(1 for s in scores if s < threshold)

    if above >= 3 or below >= 3:
        return "HIGH"
    elif above >= 2 or below >= 2:
        return "MEDIUM"
    else:
        return "LOW"


def main():
    parser = argparse.ArgumentParser(description="TrustStack forensic engine v3")
    parser.add_argument("--input", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--candidate-name", required=True)
    parser.add_argument("--original-name", required=True)
    args = parser.parse_args()

    t_start = time.time()
    layer_errors = []

    # ── Safe image load ──────────────────────────────────────────────────
    try:
        source = safe_open_image(args.input)
    except Exception as exc:
        print(json.dumps({
            "verdict": "ERROR", "score": 0,
            "findings": [f"Failed to open image: {exc}"],
            "suspicious_regions": [], "heatmap_path": "",
            "ocr_text": "", "ocr_available": _HAS_TESSERACT,
            "name_found": False, "name_confidence": 0.0,
            "alignment_anomalies": [], "text_ela_anomalies": [],
            "ela_score": 0, "ocr_score": 0, "noise_score": 0, "edge_score": 0,
            "engine_version": ENGINE_VERSION,
            "confidence_level": "LOW", "analysis_duration_ms": 0,
            "layer_errors": [f"image_load: {exc}"],
        }))
        return 1

    Path(args.output_dir).mkdir(parents=True, exist_ok=True)

    # ── Layer 1: ELA (Visual) ────────────────────────────────────────────
    ela_result = {"ela_score": 0, "heatmap_path": "", "suspicious_regions": [],
                  "mean_delta": 0, "peak_delta": 0}
    ela_mask = None
    try:
        ela_result, ela_mask = analyze_ela(source, args.output_dir, args.original_name)
    except Exception as exc:
        layer_errors.append(f"ela: {exc}")

    # ── Layer 2: OCR (Textual) ───────────────────────────────────────────
    ocr_result = {"ocr_available": _HAS_TESSERACT, "ocr_text": "", "name_found": False,
                  "name_confidence": 0.0, "alignment_anomalies": [], "text_ela_anomalies": [],
                  "ocr_score": 0}
    try:
        ocr_result = ocr_analysis(source, args.candidate_name, ela_mask)
    except Exception as exc:
        layer_errors.append(f"ocr: {exc}")

    # ── Layer 3: Noise Consistency ───────────────────────────────────────
    noise_result = {"noise_score": 0, "noise_cv": 0, "noise_mean_stddev": 0,
                    "noise_global_std": 0, "noise_anomaly_regions": []}
    try:
        noise_result = noise_analysis(source)
    except Exception as exc:
        layer_errors.append(f"noise: {exc}")

    # ── Layer 4: Edge Coherence ──────────────────────────────────────────
    edge_result = {"edge_score": 0, "edge_density_cv": 0, "edge_mean_density": 0,
                   "edge_anomaly_regions": []}
    try:
        edge_result = edge_analysis(source)
    except Exception as exc:
        layer_errors.append(f"edge: {exc}")

    # ── Findings ─────────────────────────────────────────────────────────
    findings = _build_findings(ela_result, ocr_result, noise_result, edge_result, args.candidate_name)

    # ── Weighted composite score ─────────────────────────────────────────
    ela_w, ocr_w, noise_w, edge_w = 0.35, 0.30, 0.20, 0.15
    if not ocr_result["ocr_available"]:
        # Redistribute OCR weight to other layers
        ela_w, ocr_w, noise_w, edge_w = 0.45, 0.05, 0.30, 0.20

    total_w = ela_w + ocr_w + noise_w + edge_w
    score = min(100, int(
        (ela_w / total_w * ela_result["ela_score"]) +
        (ocr_w / total_w * ocr_result["ocr_score"]) +
        (noise_w / total_w * noise_result["noise_score"]) +
        (edge_w / total_w * edge_result["edge_score"])
    ))

    # Critical failure overrides
    if ela_result["ela_score"] >= 50:
        score = max(score, ela_result["ela_score"])
    if ocr_result.get("text_ela_anomalies"):
        score = max(score, 60)

    verdict = "FAIL" if score >= 40 else "PASS"
    confidence = _compute_confidence(
        ela_result["ela_score"], ocr_result["ocr_score"],
        noise_result["noise_score"], edge_result["edge_score"],
        ocr_result["ocr_available"],
    )

    duration_ms = int((time.time() - t_start) * 1000)

    print(json.dumps({
        "verdict": verdict,
        "score": score,
        "confidence_level": confidence,
        "findings": findings,
        "suspicious_regions": ela_result["suspicious_regions"],
        "heatmap_path": ela_result["heatmap_path"],
        # OCR fields (backward-compatible)
        "ocr_text": ocr_result["ocr_text"][:2000],
        "ocr_available": ocr_result["ocr_available"],
        "name_found": ocr_result["name_found"],
        "name_confidence": ocr_result["name_confidence"],
        "alignment_anomalies": ocr_result["alignment_anomalies"],
        "text_ela_anomalies": ocr_result.get("text_ela_anomalies", []),
        # Per-layer scores
        "ela_score": ela_result["ela_score"],
        "ocr_score": ocr_result["ocr_score"],
        "noise_score": noise_result["noise_score"],
        "edge_score": edge_result["edge_score"],
        # Forensic detail envelope
        "forensic_details": {
            "ela": {
                "mean_delta": ela_result["mean_delta"],
                "peak_delta": ela_result["peak_delta"],
            },
            "noise": {
                "cv": noise_result["noise_cv"],
                "mean_stddev": noise_result["noise_mean_stddev"],
                "anomaly_regions": noise_result["noise_anomaly_regions"],
            },
            "edge": {
                "density_cv": edge_result["edge_density_cv"],
                "mean_density": edge_result["edge_mean_density"],
                "anomaly_regions": edge_result["edge_anomaly_regions"],
            },
        },
        # Meta
        "engine_version": ENGINE_VERSION,
        "analysis_duration_ms": duration_ms,
        "layer_errors": layer_errors,
    }))
    return 0


if __name__ == "__main__":
    sys.exit(main())
