#!/usr/bin/env python3
"""
TrueSeal Forensic Engine v2
Two-layer document verification:
  1. Visual Layer  — Error Level Analysis (ELA) + compression heatmap
  2. Textual Layer — OCR text extraction + bounding box alignment analysis

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
from pathlib import Path

import numpy as np
from PIL import Image, ImageChops, ImageDraw, ImageEnhance, ImageFilter, ImageOps, ImageStat

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


# ══════════════════════════════════════════════════════════════════════════════
#  LAYER 1 — Error Level Analysis (Visual)
# ══════════════════════════════════════════════════════════════════════════════

def analyze_ela(input_path: str, output_dir: str, original_name: str):
    """Re-save as JPEG and diff to expose compression inconsistencies."""
    source = Image.open(input_path).convert("RGB")

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
    return heat_score, str(output_path), suspicious_regions(grayscale), grayscale


def suspicious_regions(mask: Image.Image) -> list[dict]:
    """Divide image into a 4×4 grid and find the hottest zones."""
    width, height = mask.size
    regions = []
    cols = 4
    rows = 4
    for row in range(rows):
        for col in range(cols):
            left = int(width * col / cols)
            upper = int(height * row / rows)
            right = int(width * (col + 1) / cols)
            lower = int(height * (row + 1) / rows)
            crop = mask.crop((left, upper, right, lower))
            intensity = ImageStat.Stat(crop).mean[0]
            if intensity >= 28:
                regions.append(
                    {
                        "label": f"zone-{row + 1}-{col + 1}",
                        "x": left,
                        "y": upper,
                        "width": right - left,
                        "height": lower - upper,
                        "intensity": round(intensity, 2),
                    }
                )

    return sorted(regions, key=lambda item: item["intensity"], reverse=True)[:6]


# ══════════════════════════════════════════════════════════════════════════════
#  LAYER 2 — Textual Analysis (OCR + Alignment)
# ══════════════════════════════════════════════════════════════════════════════

def ocr_analysis(input_path: str, candidate_name: str, ela_mask: Image.Image | None = None) -> dict:
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
        # Fallback: enhanced filename/metadata heuristics
        result["ocr_text"] = "(Tesseract not available — using metadata analysis)"
        result["ocr_score"] = _filename_heuristic_score(candidate_name, Path(input_path).name)
        return result

    img = Image.open(input_path)
    data = pytesseract.image_to_data(img, output_type=pytesseract.Output.DICT)

    # ── 1. Full text extraction ──────────────────────────────────────────
    words = []
    for i in range(len(data["text"])):
        text = data["text"][i].strip()
        conf = int(data["conf"][i]) if data["conf"][i] != "-1" else -1
        if text and conf > 0:
            words.append({
                "text": text,
                "conf": conf,
                "left": data["left"][i],
                "top": data["top"][i],
                "width": data["width"][i],
                "height": data["height"][i],
                "line_num": data["line_num"][i],
                "block_num": data["block_num"][i],
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
    # Group words by line and check for vertical misalignment
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
            # Flag if a word is offset by more than 30% of the median height
            if median_height > 0 and (top_deviation / median_height > 0.3 or height_deviation / median_height > 0.4):
                anomalies.append({
                    "word": w["text"],
                    "expected_y": median_top,
                    "actual_y": w["top"],
                    "deviation_px": top_deviation,
                    "x": w["left"],
                    "y": w["top"],
                    "width": w["width"],
                    "height": w["height"],
                })

    result["alignment_anomalies"] = anomalies[:8]

    # ── 4. Cross-reference text regions with ELA mask ────────────────────
    if ela_mask is not None:
        text_ela_hits = []
        for w in words:
            if w["conf"] < 30:
                continue
            region = ela_mask.crop((
                w["left"],
                w["top"],
                min(w["left"] + w["width"], ela_mask.width),
                min(w["top"] + w["height"], ela_mask.height),
            ))
            region_intensity = ImageStat.Stat(region).mean[0]
            # Check if name tokens appear in suspiciously hot regions
            if region_intensity >= 25 and any(t in w["text"].lower() for t in name_tokens):
                text_ela_hits.append({
                    "word": w["text"],
                    "ela_intensity": round(region_intensity, 2),
                    "x": w["left"],
                    "y": w["top"],
                })

        result["text_ela_anomalies"] = text_ela_hits[:6]

    # ── 5. Composite OCR score ───────────────────────────────────────────
    score = 0
    # Name NOT found → likely fake or heavily edited
    if name_tokens and not result["name_found"]:
        score += 25
    elif result["name_confidence"] < 50:
        score += 15

    # Alignment anomalies
    score += min(30, len(anomalies) * 8)

    # Name appearing in ELA hotspot = strong tampering signal
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
#  COMPOSITE SCORING & OUTPUT
# ══════════════════════════════════════════════════════════════════════════════

def main():
    parser = argparse.ArgumentParser(description="TrueSeal forensic engine v2")
    parser.add_argument("--input", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--candidate-name", required=True)
    parser.add_argument("--original-name", required=True)
    args = parser.parse_args()

    try:
        Path(args.output_dir).mkdir(parents=True, exist_ok=True)

        # ── Layer 1: Visual (ELA) ────────────────────────────────────────
        heat_score, heatmap_path, regions, ela_mask = analyze_ela(
            args.input, args.output_dir, args.original_name
        )

        # ── Layer 2: Textual (OCR + Alignment) ──────────────────────────
        ocr_result = ocr_analysis(args.input, args.candidate_name, ela_mask)

        # ── Findings ─────────────────────────────────────────────────────
        findings = []

        # ELA findings
        if heat_score >= 50:
            findings.append("ELA detected high-compression variance consistent with local pixel edits.")
        elif heat_score >= 32:
            findings.append("ELA detected moderate compression variance; manual review recommended.")
        elif not ocr_result.get("text_ela_anomalies"):
            findings.append("ELA compression profile is broadly consistent across the certificate.")

        # OCR findings
        if ocr_result["ocr_available"]:
            if not ocr_result["name_found"]:
                findings.append(
                    f"OCR could not locate the candidate name \"{args.candidate_name}\" in the document text. "
                    "This may indicate a fabricated certificate."
                )
            elif ocr_result["name_confidence"] < 100:
                findings.append(
                    f"OCR found partial match for candidate name ({ocr_result['name_confidence']}% tokens matched)."
                )
            else:
                findings.append("OCR confirmed candidate name is present in the document text.")

            if ocr_result["alignment_anomalies"]:
                anomaly_words = ", ".join(a["word"] for a in ocr_result["alignment_anomalies"][:3])
                findings.append(
                    f"Bounding box analysis found {len(ocr_result['alignment_anomalies'])} words with "
                    f"anomalous vertical alignment: {anomaly_words}. This is consistent with digitally spliced text."
                )

            if ocr_result["text_ela_anomalies"]:
                hot_words = ", ".join(a["word"] for a in ocr_result["text_ela_anomalies"][:3])
                findings.append(
                    f"Critical: ELA hotspots overlap with candidate name text regions ({hot_words}). "
                    "Strong evidence of pixel-level manipulation at the name location."
                )
        else:
            # Filename heuristics
            if ocr_result["ocr_score"] > 0:
                findings.append("Filename metadata contains indicators flagged for audit correlation.")

        # ── Weighted composite score ─────────────────────────────────────
        ela_weight = 0.50
        ocr_weight = 0.50 if ocr_result["ocr_available"] else 0.20
        # Normalize weights
        total_weight = ela_weight + ocr_weight
        ela_weight /= total_weight
        ocr_weight /= total_weight

        score = min(100, int(
            (ela_weight * heat_score) +
            (ocr_weight * ocr_result["ocr_score"])
        ))

        # Critical failure overrides: 
        # If ELA detects high variance, ensure the score is at least the heat_score.
        if heat_score >= 50:
            score = max(score, heat_score)

        # If ELA hotspots overlap with text regions, this is a definite forgery.
        if ocr_result.get("text_ela_anomalies"):
            score = max(score, 60)

        verdict = "FAIL" if score >= 40 else "PASS"

        print(
            json.dumps(
                {
                    "verdict": verdict,
                    "score": score,
                    "findings": findings,
                    "suspicious_regions": regions,
                    "heatmap_path": heatmap_path,
                    "ocr_text": ocr_result["ocr_text"][:2000],
                    "ocr_available": ocr_result["ocr_available"],
                    "name_found": ocr_result["name_found"],
                    "name_confidence": ocr_result["name_confidence"],
                    "alignment_anomalies": ocr_result["alignment_anomalies"],
                    "text_ela_anomalies": ocr_result.get("text_ela_anomalies", []),
                    "ela_score": heat_score,
                    "ocr_score": ocr_result["ocr_score"],
                }
            )
        )
    except Exception as exc:
        print(json.dumps({
            "verdict": "ERROR",
            "score": 0,
            "findings": [str(exc)],
            "suspicious_regions": [],
            "heatmap_path": "",
            "ocr_text": "",
            "ocr_available": _HAS_TESSERACT,
            "name_found": False,
            "name_confidence": 0.0,
            "alignment_anomalies": [],
            "text_ela_anomalies": [],
            "ela_score": 0,
            "ocr_score": 0,
        }))
        return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
