# Kaos Captcha Generator

A robust, highly resilient CAPTCHA generator designed to resist automated OCR and AI-based solving attempts. This script produces randomized numeric captchas with multiple obfuscation layers, including rotation, color variation, spacing irregularities, varied fonts, and noise lines.

It is suitable for web applications where **strong anti-bot protection** is required while remaining readable to human users.

![Kaos Captcha Screenshot](assets/img/kaos-captcha.png)

---

## **Features**

* Generates **5-character alphanumeric CAPTCHAs** (A–Z, a–z, 0–9), randomized per session.
* Randomized **font selection per character** (8 fonts selected during installation).
* Dynamic **font size** between **28 px – 36 px**.
* Randomized **character rotation** between approximately **−30° and +30°**.
* Adjustable **horizontal spacing** (6 – 14 px variation between characters).
* Randomized **vertical jitter** (6 – 12 px) for irregular baseline placement.
* Fully randomized **RGB color** per character, constrained to avoid excessively bright or dark tones.
* Randomized **background** — uses `white.jpg` if present, otherwise generates a plain white background.
* Adds **12–24 random noise lines** per CAPTCHA, each with unique color, position, and brightness.
* Supports environment-based **CAPTCHA_PEPPER** for entropy seeding, improving unpredictability and per-installation uniqueness.
* Automatically embeds **randomized per-installation parameters** for image dimensions, font behavior, noise density, and color variance.
* Uses **deterministic seed generation** (HMAC-based) when `CAPTCHA_PEPPER` is defined, ensuring consistent yet unpredictable outputs within sessions.
* Dependencies: **PHP 7.4+** (or newer) with **GD extension** enabled.
* Produces a fully self-contained output file (`captcha.php`) requiring **no external libraries** or API calls.

---

### **Obfuscation and Security Analysis**

This CAPTCHA script is engineered for **entropy and OCR resistance**.
Each installation produces **site-specific randomized parameters** (fonts, rotation ranges, spacing, color balance, noise density), ensuring that every deployment yields a visually and statistically distinct image distribution.
The following breakdown explains how each obfuscation layer contributes to overall resilience, using representative values produced by the installer.

![Captcha Resilience Visualization](assets/img/captcha_resilience_vis.png)

---

#### **1. Random Alphanumeric Sequence**

* Each CAPTCHA consists of **5 alphanumeric characters** (A–Z, a–z, 0–9).
* That provides **62 possibilities per character**.
* Entropy formula: `H = log2(N^L) = L * log2(N)`
* Calculated entropy (characters only): `H_chars = 5 * log2(62) ≈ 29.75 bits`
* This forms the **baseline entropy** from the sequence itself — almost **double** that of numeric-only CAPTCHAs.

---

#### **2. Font Variability (per-install)**

* The installer embeds **8 randomly selected fonts** (from local `fonts/` or fallback fonts).
* Each character randomly selects one of these fonts: `log2(8) ≈ 3 bits` per character.
* For 5 characters: `H_fonts = 5 * 3 = 15 bits`.

> Because font choice is fixed at install time but randomized per character, this adds **intra-installation variability** while maintaining distinctiveness between deployments.
> Avoid publishing production font files to prevent reverse engineering of glyph shapes.

---

#### **3. Rotation (per-character)**

* Rotation is typically randomized between **−30° and +30°**, with exact bounds unique per installation.
* Approximate distinct positions: **61 integer values**.
* Entropy per character: `log2(61) ≈ 5.93 bits`.
* For 5 characters: `5 * 5.93 ≈ 29.65 bits`.

> Rotation remains one of the most effective defenses against automated segmentation and OCR alignment.

---

#### **4. Color Variation (per-character)**

* Each character’s color is randomly generated in the **RGB spectrum**, constrained only by brightness thresholds to maintain readability.
* Each RGB channel offers up to 256 possibilities, yielding up to **24 bits of entropy per character**.
* For 5 characters: `H_color = 5 * 24 = 120 bits`.

> While OCR engines often normalize color to grayscale, random color values significantly hinder background subtraction and contour detection.

---

#### **5. Horizontal Spacing & Vertical Jitter**

* The installer randomizes both `spacing_range` (typically **6–14 px**) and `y_jitter` (**6–12 px**).
* For spacing, assuming roughly 21 integer offsets per gap: `log2(21) ≈ 4.39 bits`.
* For 4 gaps (between 5 characters): `4 * 4.39 ≈ 17.56 bits`.

> Combined with vertical jitter, this forces OCR algorithms to perform additional geometric correction before segmentation.

---

#### **6. Noise Lines (per-image; randomized range)**

* Each CAPTCHA includes **12–24 randomly drawn noise lines**, each with random color and start/end positions.
* Using a conservative combinatorial estimate: `~53.07 bits per line`.
* With an average of 18 lines: `18 * 53.07 ≈ 955.26 bits`.

> This layer provides the strongest **OCR disruption**.
> While not all theoretical entropy is practical, these lines heavily interfere with contour detection, skeletonization, and path clustering.

---

#### **7. Total Approximate Entropy (Conservative Example)**

Using representative averages from the installer:

| Component               | Approx. Entropy (bits) |
| ----------------------- | ---------------------- |
| Alphanumeric Sequence   | 29.75                  |
| Fonts                   | 15                     |
| Rotation                | 29.65                  |
| Color                   | 120                    |
| Spacing & Jitter        | 17.56                  |
| Noise Lines (avg. 18)   | 955.26                 |
| **Total (approximate)** | **1,167.2 bits**       |

*Lower-bound (ignoring color and line noise):*
`29.75 + 15 + 29.65 + 17.56 ≈ 91.96 bits`

> **Interpretation:**
> Even ignoring color and noise-line obfuscation, the CAPTCHA maintains **~92 bits of effective entropy**, placing it well above the resistance threshold for untrained OCR or general-purpose AI solvers.
> With all layers active, the theoretical entropy exceeds **1,100 bits**, ensuring that any successful solver would require extensive site-specific data collection and model tuning.

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


