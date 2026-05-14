#!/usr/bin/env python3
"""
Generate a full test dataset of certificate images for TrueSeal demo.

Produces 10 clean + 10 forged certificates for each of the 4 seeded universities.
Total: 80 certificates (40 clean, 40 forged).

Usage:
  python3 generate_test_certs.py [--output-dir ./test_images]
"""
import argparse
import os
import random
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

# ── Universities (matching DatabaseSeeder) ───────────────────────────────────
UNIVERSITIES = [
    {"name": "University of Lagos", "code": "UNILAG", "initials": "UL", "color": (26, 60, 110)},
    {"name": "University of Ibadan", "code": "UI", "initials": "UI", "color": (80, 20, 20)},
    {"name": "Covenant University", "code": "CU", "initials": "CU", "color": (75, 0, 130)},
    {"name": "Ahmadu Bello University", "code": "ABU", "initials": "AB", "color": (0, 80, 50)},
]

DEGREES = [
    "BACHELOR OF SCIENCE (B.Sc.) IN COMPUTER SCIENCE",
    "BACHELOR OF ENGINEERING (B.Eng.) IN ELECTRICAL ENGINEERING",
    "BACHELOR OF ARTS (B.A.) IN ENGLISH LITERATURE",
    "BACHELOR OF SCIENCE (B.Sc.) IN ECONOMICS",
    "BACHELOR OF SCIENCE (B.Sc.) IN BIOCHEMISTRY",
    "BACHELOR OF LAW (LL.B.)",
    "BACHELOR OF MEDICINE (MBBS)",
    "BACHELOR OF SCIENCE (B.Sc.) IN PHYSICS",
    "BACHELOR OF SCIENCE (B.Sc.) IN MATHEMATICS",
    "BACHELOR OF ARTS (B.A.) IN POLITICAL SCIENCE",
]

HONOURS = [
    "First Class Honours",
    "Second Class Honours (Upper Division)",
    "Second Class Honours (Lower Division)",
    "Third Class Honours",
]

# Nigerian first names, middle names, and surnames
FIRST_NAMES = [
    "Adebayo", "Chidinma", "Oluwaseun", "Emeka", "Fatima",
    "Tunde", "Ngozi", "Yusuf", "Aisha", "Obinna",
    "Kelechi", "Amina", "Damilola", "Ibrahim", "Blessing",
    "Chukwuemeka", "Folake", "Abdullahi", "Grace", "Ifeanyi",
]

MIDDLE_NAMES = [
    "Oluwaseun", "Chukwudi", "Adaeze", "Olumide", "Nkechi",
    "Babatunde", "Chiamaka", "Olamide", "Nneka", "Olayinka",
    "Ifeoma", "Oluwadamilare", "Uchechi", "Ayodele", "Obiageli",
    "Temitope", "Chisom", "Oluwafemi", "Adaora", "Kayode",
]

SURNAMES = [
    "Okonkwo", "Adeyemi", "Nnamdi", "Okafor", "Bello",
    "Ogundimu", "Eze", "Abubakar", "Oni", "Chukwuma",
    "Adeniyi", "Okwu", "Lawal", "Nwachukwu", "Abdulrahman",
    "Akindele", "Igwe", "Salami", "Adekunle", "Obi",
]

# ── Layout constants ─────────────────────────────────────────────────────────
WIDTH, HEIGHT = 1200, 850
BG_COLOR = (255, 253, 245)
ACCENT_COLOR = (180, 150, 60)
TEXT_COLOR = (20, 20, 40)
GRAY = (100, 100, 120)


def get_font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf" if bold else "/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf",
    ]
    for path in candidates:
        if os.path.exists(path):
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def random_name(rng: random.Random, exclude: set | None = None) -> str:
    """Generate a realistic Nigerian full name."""
    for _ in range(50):
        name = f"{rng.choice(FIRST_NAMES)} {rng.choice(MIDDLE_NAMES)} {rng.choice(SURNAMES)}"
        if exclude is None or name not in exclude:
            return name
    return f"{rng.choice(FIRST_NAMES)} {rng.choice(MIDDLE_NAMES)} {rng.choice(SURNAMES)}"


def draw_certificate(uni: dict, name: str, degree: str, honours: str, year: int, serial: str) -> Image.Image:
    """Draw a complete certificate image."""
    img = Image.new("RGB", (WIDTH, HEIGHT), BG_COLOR)
    draw = ImageDraw.Draw(img)
    primary = uni["color"]

    # Borders
    draw.rectangle([(15, 15), (WIDTH - 16, HEIGHT - 16)], outline=primary, width=3)
    draw.rectangle([(25, 25), (WIDTH - 26, HEIGHT - 26)], outline=ACCENT_COLOR, width=1)

    # Crest
    cx, cy = WIDTH // 2, 80
    draw.ellipse([(cx - 35, cy - 35), (cx + 35, cy + 35)], fill=ACCENT_COLOR, outline=primary, width=2)
    draw.text((cx, cy), uni["initials"], fill=(255, 255, 255), font=get_font(22, True), anchor="mm")

    # University name
    draw.text((WIDTH // 2, 145), uni["name"].upper(), fill=primary, font=get_font(28, True), anchor="mm")
    draw.text((WIDTH // 2, 175), "SENATE OF THE UNIVERSITY", fill=GRAY, font=get_font(14), anchor="mm")

    # Certificate type
    draw.text((WIDTH // 2, 225), "CERTIFICATE OF DEGREE", fill=TEXT_COLOR, font=get_font(20, True), anchor="mm")

    # Body
    body = get_font(14)
    draw.text((WIDTH // 2, 280), "This is to certify that", fill=GRAY, font=body, anchor="mm")

    # Name
    draw.text((WIDTH // 2, 330), name, fill=primary, font=get_font(32, True), anchor="mm")

    draw.text((WIDTH // 2, 390), "having fulfilled all the requirements prescribed by the Senate", fill=GRAY, font=body, anchor="mm")
    draw.text((WIDTH // 2, 420), f"of the {uni['name']}, was admitted to the degree of", fill=GRAY, font=body, anchor="mm")

    # Degree
    draw.text((WIDTH // 2, 465), degree, fill=TEXT_COLOR, font=get_font(18, True), anchor="mm")
    draw.text((WIDTH // 2, 495), honours, fill=GRAY, font=body, anchor="mm")

    # Date
    months = ["January", "March", "June", "July", "September", "December"]
    month = months[year % len(months)]
    draw.text((WIDTH // 2, 545), f"Awarded on the 15th day of {month}, {year}", fill=GRAY, font=body, anchor="mm")

    # Signatures
    draw.line([(150, 650), (400, 650)], fill=GRAY, width=1)
    draw.line([(800, 650), (1050, 650)], fill=GRAY, width=1)
    draw.text((275, 658), "Vice-Chancellor", fill=GRAY, font=get_font(11), anchor="mm")
    draw.text((925, 658), "Registrar", fill=GRAY, font=get_font(11), anchor="mm")

    # Serial
    draw.text((WIDTH // 2, HEIGHT - 50), f"Cert. No: {serial}", fill=GRAY, font=get_font(10), anchor="mm")

    return img


def forge_certificate(img: Image.Image, uni: dict, forged_name: str, forged_year: int, temp_dir: Path) -> Image.Image:
    """
    Take a clean certificate image, save it as low-quality JPEG to bake in
    compression artifacts, then splice a new name and year onto it.
    """
    # Save at low quality to create compression artifacts
    temp_path = temp_dir / "_temp_forge.jpg"
    img.save(temp_path, "JPEG", quality=random.randint(55, 70))

    # Re-open (now has double-compression potential)
    forged = Image.open(temp_path).convert("RGB")
    draw = ImageDraw.Draw(forged)

    # Erase name region
    name_region = (WIDTH // 2 - 320, 295, WIDTH // 2 + 320, 365)
    draw.rectangle(name_region, fill=BG_COLOR)

    # Erase year region
    year_region = (WIDTH // 2 - 240, 525, WIDTH // 2 + 240, 565)
    draw.rectangle(year_region, fill=BG_COLOR)

    # Blur edges to simulate forgery blending
    patch = forged.crop(name_region)
    patch = patch.filter(ImageFilter.GaussianBlur(radius=0.5))
    forged.paste(patch, (name_region[0], name_region[1]))

    # Draw forged name
    draw.text((WIDTH // 2, 330), forged_name, fill=uni["color"], font=get_font(32, True), anchor="mm")

    # Draw forged year
    body = get_font(14)
    months = ["January", "March", "June", "July", "September", "December"]
    month = months[forged_year % len(months)]
    draw.text((WIDTH // 2, 545), f"Awarded on the 15th day of {month}, {forged_year}", fill=GRAY, font=body, anchor="mm")

    # Clean up
    try:
        os.unlink(temp_path)
    except OSError:
        pass

    return forged


def main():
    parser = argparse.ArgumentParser(description="Generate test certificate dataset for TrueSeal")
    parser.add_argument("--output-dir", default="./test_images", help="Output directory")
    args = parser.parse_args()

    output_dir = Path(args.output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)

    rng = random.Random(42)  # Reproducible
    manifest = []

    print("Generating test certificate dataset...\n")

    for uni in UNIVERSITIES:
        uni_dir = output_dir / uni["code"].lower()
        (uni_dir / "clean").mkdir(parents=True, exist_ok=True)
        (uni_dir / "forged").mkdir(parents=True, exist_ok=True)

        used_names: set[str] = set()
        print(f"── {uni['name']} ({uni['code']}) ──")

        for i in range(1, 11):
            real_name = random_name(rng, used_names)
            used_names.add(real_name)
            degree = rng.choice(DEGREES)
            honours = rng.choice(HONOURS)
            year = rng.randint(2019, 2024)
            serial = f"{uni['code']}/{year}/{''.join(rng.choices('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', k=5))}"

            # ── Clean certificate ────────────────────────────────────
            clean_img = draw_certificate(uni, real_name, degree, honours, year, serial)
            clean_path = uni_dir / "clean" / f"{uni['code'].lower()}_clean_{i:02d}.jpg"
            clean_img.save(clean_path, "JPEG", quality=92)

            manifest.append({
                "file": str(clean_path.relative_to(output_dir)),
                "candidate_name": real_name,
                "institution": uni["name"],
                "institution_code": uni["code"],
                "type": "clean",
                "expected_verdict": "PASS",
            })

            # ── Forged certificate ───────────────────────────────────
            forged_name = random_name(rng, used_names)
            used_names.add(forged_name)
            forged_year = rng.choice([y for y in range(2018, 2025) if y != year])

            forged_img = forge_certificate(clean_img, uni, forged_name, forged_year, uni_dir)
            forged_path = uni_dir / "forged" / f"{uni['code'].lower()}_forged_{i:02d}.jpg"
            forged_img.save(forged_path, "JPEG", quality=rng.randint(80, 88))

            manifest.append({
                "file": str(forged_path.relative_to(output_dir)),
                "candidate_name": forged_name,
                "institution": uni["name"],
                "institution_code": uni["code"],
                "type": "forged",
                "expected_verdict": "FAIL",
                "original_name": real_name,
                "original_year": year,
                "forged_year": forged_year,
            })

            print(f"  {i:2d}. ✓ clean: {real_name} ({year})  |  ✗ forged: {forged_name} ({forged_year})")

        print()

    # Write manifest JSON
    import json
    manifest_path = output_dir / "manifest.json"
    with open(manifest_path, "w") as f:
        json.dump(manifest, f, indent=2)

    total_clean = sum(1 for m in manifest if m["type"] == "clean")
    total_forged = sum(1 for m in manifest if m["type"] == "forged")

    print(f"═══════════════════════════════════════════════════════")
    print(f"  Total generated: {len(manifest)} certificates")
    print(f"    Clean: {total_clean}  |  Forged: {total_forged}")
    print(f"  Manifest: {manifest_path}")
    print(f"")
    print(f"  Use manifest.json to automate testing or to look up")
    print(f"  the correct candidate name for each upload.")
    print(f"═══════════════════════════════════════════════════════")


if __name__ == "__main__":
    main()
