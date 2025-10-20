# Kaos Captcha Generator

A robust, highly resilient CAPTCHA generator designed to resist automated OCR and AI-based solving attempts. This script produces randomized numeric captchas with multiple obfuscation layers, including rotation, color variation, spacing irregularities, varied fonts, and noise lines.

It is suitable for web applications where **strong anti-bot protection** is required while remaining readable to human users.

![Kaos Captcha Screenshot](assets/img/kaos-captcha.png)

---

## **Features**

* Generates **5-digit numeric CAPTCHAs** (randomized per session).  
* Randomized **font selection per digit** (8 fonts selected during installation).  
* Dynamic **font size** between 28 px – 36 px.  
* Randomized **digit rotation range** between approximately −30° and +30°.  
* Adjustable **horizontal spacing** (6 – 14 px variation between digits).  
* Randomized **vertical jitter** (6 – 12 px) for irregular baseline placement.  
* Fully randomized **RGB color** per digit, constrained to avoid excessively bright or dark tones.  
* Randomized **background** — uses `white.jpg` if present, otherwise generates a plain white background.  
* Adds **12–24 random noise lines** per CAPTCHA, each with unique color, position, and brightness.  
* Supports environment-based **CAPTCHA_PEPPER** for entropy seeding (optional but recommended).  
* Automatically embeds **per-installation randomized parameters** for image dimensions, noise density, and color balance.  
* Dependencies: **PHP 7.4 +** (or newer) with **GD extension** enabled.  
* Fully self-contained output (`captcha.php`) that requires **no external libraries** or API calls.  

---

### Obfuscation and Security Analysis

This captcha script is designed for **entropy and OCR resistance**. The installer produces **site-specific** randomized parameters (fonts, rotation ranges, spacing, color ranges, noise density) so each deployment yields a distinct image distribution. The following breakdown describes how each obfuscation layer contributes to overall resilience and shows a conservative example calculation using typical values produced by the installer.

![Captcha Resilience Visualization](assets/img/captcha_resilience_vis.png)

---

#### 1. Random Digit Sequence

* Each captcha has **5 digits (0–9)** → `10` possibilities per digit.
* Entropy formula: `H = log2(N^L) = L * log2(N)`
* Calculated entropy (digits only): `H_digits = 5 * log2(10) ≈ 16.61 bits`
* This is the **baseline entropy** coming from the numeric sequence only.

---

#### 2. Font Variability (per-install)

* The installer selects **8 fonts** (from local `fonts/` or public fallbacks) and embeds that list into the generated `captcha.php`.
* For entropy estimation we conservatively treat font choice as independent per digit: `log2(8) ≈ 3 bits` per digit.
* For 5 digits: `H_fonts = 5 * 3 = 15 bits`.

> Note: because fonts are chosen at install time (not per-request), this adds *installation-level* variability. An attacker who obtains the exact production font files has an advantage; do not publish production fonts.

---

#### 3. Rotation (per-digit)

* Installer sets rotation range roughly **−30° to +30°** (the script randomizes the exact min/max on install but keeps it near ±30°). That gives **61 possible integer-degree angles**.
* Entropy per digit: `log2(61) ≈ 5.93 bits`.
* For 5 digits: `5 * 5.93 ≈ 29.65 bits`.

---

#### 4. Color Variation (per-digit)

* The installer constrains color brightness but still uses **full RGB randomness within those ranges** (per-digit). Each RGB channel still has up to 256 values giving up to **24 bits per digit** in theory.
* Conservative estimate for 5 digits: `H_color = 5 * 24 = 120 bits`.
* In practice OCR preprocessing may ignore color channels, so effective entropy from color is smaller — but color remains a large confounder for segmentation and line-removal heuristics.

---

#### 5. Horizontal Spacing & Vertical Jitter

* The installer randomizes `spacing_range` (typically between `6` and `14` px) and `y_jitter` (typically `6`–`12` px).
* For a conservative numeric example, use `spacing_range ≈ 10` → spacing per gap ≈ 21 integer possibilities (±10).
* Entropy per gap: `log2(21) ≈ 4.39 bits`. For 4 gaps (between 5 digits): `4 * 4.39 ≈ 17.56 bits`.
* Vertical jitter adds further segmentation uncertainty (not fully counted above).

---

#### 6. Noise Lines (per-image; randomized range)

* The installer selects a noise line count in the range **12–24** (this is randomized per install and per image). Typical installs cluster near 15–20 lines.
* Each line has randomized start/end positions and color (brightness constrained by `line_color_brightness_min`). For a conservative per-line estimate we reuse the earlier combinatorial approximation: `~53.07 bits per line` if you treat positions and color as independent.
* Using a conservative average of **18 lines** (within the installer’s range): `18 * 53.07 ≈ 955.26 bits`.
* Important: this is a **theoretical** combinatorial number. The practical effect is that lines heavily disrupt segmentation and classical OCR pipelines — this is the primary obstacle to automated solvers.

---

#### 7. Total approximate entropy (conservative example)

* Using the representative (conservative) values produced by the installer:

  * `H_digits ≈ 16.61`
  * `H_fonts  ≈ 15`
  * `H_rotation ≈ 29.65`
  * `H_color ≈ 120`
  * `H_spacing ≈ 17.56`
  * `H_lines ≈ 955.26` (using average 18 lines)

* Sum (representative):
  `H_total ≈ 16.61 + 15 + 29.65 + 120 + 17.56 + 955.26 ≈ 1153.1 bits`

* Conservative lower-bound (ignore color and lines):
  `16.61 + 15 + 29.65 + 17.56 ≈ 78.8 bits`

> **Interpretation:** the full theoretical entropy number is very large and intentionally conservative. The practical takeaway is the same: **even ignoring color and line noise**, an off-the-shelf OCR or generic AI model faces on the order of tens of bits of uncertainty (~78 bits conservatively), which is difficult to overcome without targeted training on site-specific images.

---

### 8. Testing Methodology

The generator was rigorously tested using a **tiered Python OCR testing suite**, with difficulty levels ranging from basic thresholding to advanced segmentation and line-inpainting techniques:

* **Easy**: Grayscale + Otsu thresholding
* **Medium**: Denoising + adaptive thresholding + morphological operations
* **Hard**: Line removal + color clustering + thresholding
* **Expert**: Full pipeline with segmentation, inpainting, and fallback OCR

#### Results

* **Kaos Captchas**: **100% OCR failure rate** across hundreds of generated images and multiple retries. No test or variation has solved a single captcha
* **Other captchas** (white background, black digits, no obfuscation - tested as a control to validate results of Kaos Captcha tests): **100% OCR success rate** across all difficulty levels.

---

### 9. Interpretation

* **Line noise and color variability** are the most effective defenses.
* **Rotation, fonts, and spacing** further impede automated segmentation.
* Combined, the captcha is **effectively unreadable by standard OCR** without bespoke model training.
* Theoretical maximum entropy (~1153 bits) ensures **robust security against automated attacks**.

---

# **Installation and Usage Instructions — CAPTCHA Installer**

The `install_captcha.php` script generates a **site-specific** `captcha.php` file containing randomized visual parameters and a font selection list.  
This approach ensures that each deployment’s CAPTCHA instance is unique and resistant to signature-based bypasses.

---

## **1. Prerequisites**

1. **PHP CLI** must be available.  
   Confirm by running:
   ```bash
   which php
   ```
   If PHP is not found, use the full path to your PHP binary (for example, `/opt/plesk/php/8.3/bin/php`).

2. Ensure the **target directory** (the web application’s root or form handler directory) is:
   - Writable by the user executing the script
   - Accessible by your web server (e.g., Apache, NGINX, or Plesk’s PHP handler)

3. Install the **GD extension** (required for image generation):
   ```bash
   php -m | grep gd
   ```
   If not found, install it using your package manager or enable it in Plesk’s PHP settings.

4. Optionally, create a `fonts/` directory in your target path and add `.ttf` files to it.  
   If fewer than eight fonts are found, public fallback fonts will be listed in the output.

---

## **2. Setting Up CAPTCHA_PEPPER**

`CAPTCHA_PEPPER` is an environment variable used to introduce installation-specific entropy.  
It ensures that random values (e.g., digit placement, color distribution, and rotation) differ per deployment and cannot be easily predicted or cloned.

### Example (Linux/Plesk):
Add to your environment configuration, such as `/etc/environment`:
```
CAPTCHA_PEPPER="your-long-random-secret"
```

Then reload the environment (or restart the web server):
```bash
source /etc/environment
```

This 48-character secret is sufficient and cryptographically sound for this purpose.

---

## **3. Running the Installer**

Execute from the command line:

```bash
php install_captcha.php /path/to/target/dir
```

**Example (Plesk environment):**
```bash
/opt/plesk/php/8.3/bin/php install_captcha.php /var/www/vhosts/example.com/httpdocs/
```

**Expected output:**
```
Generated /var/www/vhosts/example.com/httpdocs/captcha.php with randomized parameters.
Fonts used (update your 'fonts/' folder with these names if needed):
 - DejaVuSans-Bold.ttf
 - LiberationSans-Bold.ttf
 ...
```

---

## **4. Verifying Installation**

After installation:
- The file `/path/to/target/dir/captcha.php` should exist.
- Open `https://example.com/captcha.php` in a browser — it should render a CAPTCHA image.
- If the image fails to render, check:
  - PHP GD extension is enabled.
  - Correct permissions on the `fonts/` and `white.jpg` files (if used).
  - Error logs (`/var/log/plesk-phpXX-fpm/error.log`).

---

## **5. Using the CAPTCHA**

### **In your HTML form:**
```html
<form method="post" action="form_handler.php">
  <img src="captcha.php" alt="CAPTCHA">
  <input type="text" name="captcha" placeholder="Enter code shown above">
  <input type="submit" value="Submit">
</form>
```

### **In your form handler (form_handler.php):**
```php
<?php
session_start();
if (isset($_POST['captcha'], $_SESSION['captcha'])) {
    if ($_POST['captcha'] === $_SESSION['captcha']) {
        echo "Captcha valid – proceeding with form submission.";
    } else {
        echo "Invalid captcha. Please try again.";
    }
    unset($_SESSION['captcha']);
} else {
    echo "Captcha missing or expired.";
}
?>
```

---

## **6. Regenerating or Resetting CAPTCHA**

You can safely regenerate the site-specific CAPTCHA (for example, after updating font files or changing the pepper) by rerunning the installer:

```bash
php install_captcha.php /path/to/target/dir
```

This overwrites the previous `captcha.php` with new randomized parameters.

---

## **7. Security Notes**

- **Never commit** the generated `captcha.php` file to public repositories.  
  It contains installation-specific randomization parameters that should remain private.
- Ensure that your pepper is stored securely and not exposed in any logs or web-accessible files.
- Regenerate the CAPTCHA occasionally to vary its characteristics further.

---

## General Notes

* **Line noise and color variation** are the strongest defenses against OCR.
* **Rotation, fonts, and spacing** add additional segmentation resistance.
* The script is designed to **resist even advanced, multi-tiered automated attacks**.

---

## License

This project is licensed under the **GNU General Public License (GPL v3)** - free to use, modify, and redistribute under the same license.

---

## Security Considerations

* Resistant to off-the-shelf OCR, AI-based solvers, and segmentation attacks.
* Ensure **HTTPS** is used to prevent interception of captcha images.
* Rate-limit login endpoints to prevent brute-force attacks, even with strong captchas.

---

## Contributing

* Submit font files or new obfuscation methods for testing.
* Pull requests are welcome to add additional noise types or alternative background images.


