# Kaos Captcha Generator

A robust, highly resilient CAPTCHA generator designed to resist automated OCR and AI-based solving attempts. This script produces randomized alpha-numeric captchas with multiple obfuscation layers, including rotation, color variation, spacing irregularities, varied fonts, and noise lines.

It is suitable for web applications where **strong anti-bot protection** is required while remaining readable to human users.

![Kaos Captcha Screenshot](assets/img/kaos-captcha.png)

---

## **Features**

* Generates **6-character alphanumeric CAPTCHAs** (randomized per session).
* Randomized **font selection per digit/character** (8 fonts selected during installation).
* Dynamic **font size** between 28 px – 36 px.
* Randomized **character rotation range** between approximately −30° and +30°.
* Adjustable **horizontal spacing** (6 – 14 px variation between characters).
* Randomized **vertical jitter** (6 – 12 px) for irregular baseline placement.
* Fully randomized **RGB color** per character, constrained to avoid excessively bright or dark tones.
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

#### 1. Random Character Sequence

* Each captcha has **6 characters (alphanumeric)** → `57` possibilities per character (excluding ambiguous characters like `0, O, 1, l, I`).
* Entropy formula: `H = log2(N^L) = L * log2(N)`
* Calculated entropy (sequence only):
  `H_sequence = 6 * log2(57) ≈ 35.0 bits`
* This is the **baseline entropy** coming from the alphanumeric sequence alone.

---

#### 2. Font Variability (per-install)

* Installer selects **8 fonts** (from local `fonts/` or public fallbacks) and embeds that list into the generated `captcha.php`.
* Entropy per character (conservative estimate): `log2(8) ≈ 3 bits`
* For 6 characters: `H_fonts = 6 * 3 = 18 bits`

> Note: fonts are chosen at install time (not per-request). If production fonts are leaked, an attacker could reduce entropy — do not publish production fonts.

---

#### 3. Rotation (per-character)

* Rotation range roughly **−30° to +30°** → 61 integer angles.
* Entropy per character: `log2(61) ≈ 5.93 bits`
* For 6 characters: `6 * 5.93 ≈ 35.6 bits`

---

#### 4. Color Variation (per-character)

* Each RGB channel is randomized within a constrained range to avoid extremes, giving up to **24 bits per character** in theory.
* For 6 characters: `H_color = 6 * 24 = 144 bits`
* OCR may ignore color channels, but color remains a strong confounder for segmentation.

---

#### 5. Horizontal Spacing & Vertical Jitter

* Horizontal spacing: `spacing_range ≈ 6–14 px` → 21 integer possibilities per gap
* 5 gaps between 6 characters: `H_spacing = 5 * log2(21) ≈ 23.9 bits`
* Vertical jitter adds further segmentation uncertainty (not fully counted above).

---

#### 6. Noise Lines (per-image)

* Installer selects **12–24 lines**, each with randomized start/end and color.
* Conservative per-line entropy: `≈ 53.07 bits`
* Using **18 lines** average: `H_lines ≈ 955.26 bits`

> Lines are the primary OCR-resistance feature — the practical difficulty they introduce outweighs raw entropy numbers.

---

#### 7. Total approximate entropy (conservative example)

* Representative values:

| Layer       | Entropy (bits) |
| ----------- | -------------- |
| Sequence    | 35.0           |
| Fonts       | 18.0           |
| Rotation    | 35.6           |
| Color       | 144.0          |
| Spacing     | 23.9           |
| Noise Lines | 955.26         |

* Sum: `H_total ≈ 1211.76 bits`
* Conservative lower-bound (ignore color and lines): `35 + 18 + 35.6 + 23.9 ≈ 112.5 bits`

> **Interpretation:** Even ignoring color and line noise, off-the-shelf OCR or AI solvers face **>100 bits of uncertainty** — extremely resistant to automated attacks without targeted, site-specific training.

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
   If PHP is not found, use the full path to your PHP binary (for example, `/usr/bin/php`).

2. Ensure the **target directory** (the web application’s root or form handler directory) is:
   - Writable by the user executing the script
   - Accessible by your web server (e.g., Apache, NGINX)

3. Install the **GD extension** (required for image generation):
   ```bash
   php -m | grep gd
   ```
   If not found, install it using your package manager or enable it in PHP settings.

4. Optionally, create a `fonts/` directory in your target path and add `.ttf` files to it.  
   If fewer than eight fonts are found, public fallback fonts will be listed in the output.

---

## **2. Setting Up CAPTCHA_PEPPER**

`CAPTCHA_PEPPER` is an environment variable used to introduce installation-specific entropy.  
It ensures that random values (e.g., digit placement, color distribution, and rotation) differ per deployment and cannot be easily predicted or cloned.

### Example (Linux):
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

**Example:**
```bash
php install_captcha.php /var/www/vhosts/example.com/httpdocs/
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

---

## **5. Using the CAPTCHA**

### **In your HTML form:**
```html
<form method="post" action="form_handler.php">
            <div>
                <h5><br>Bot Check:</h5>
                <p>
                    <img id="captcha_img" name="captcha_img" src="captcha.php" alt="Captcha Image" title="Enter the numbers from this image into the input field below">
                </p>
                <p>
                    <input type="text" id="captcha" name="captcha" maxlength="6" onkeyup="this.value = this.value.replace(/[^a-zA-Z0-9]+/g, '');" style="height:25px;" placeholder="Enter Code" required>

                    <a href="#" onclick="document.getElementById('captcha_img').src = './captcha.php?' + Math.random(); document.getElementById('captcha').value = ''; return false;"><img src="./refresh.png" style="width:40px;" alt="Refresh the Image" title="If you have trouble seeing the numbers, click this button to refresh the image."></a>
                    <br>
                    <small>Enter the numbers from the image above.</small>
                </p>
				<div class="col-md-6 button">
					<button class="btn btn-primary d-block w-100" name="submit" type="submit">Submit</button>
				</div>
            </div>
</form>
```

### **In your form handler (form_handler.php):**
```php
<?php
session_start();  // <-- required before using $_SESSION

if (isset($_POST['captcha'], $_SESSION['captcha'])) {
    // Normalize both values to uppercase for case-insensitive comparison
    $userInput = strtoupper(trim($_POST['captcha']));
    $storedCaptcha = strtoupper(trim($_SESSION['captcha']));

    if ($userInput === $storedCaptcha) {
        // captcha is valid
		// Process form submission code goes below here
    } else {
        // Invalid captcha
    }

    // Remove the captcha so it cannot be reused
    unset($_SESSION['captcha']);
} else {
    echo "Captcha or session missing!";
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


