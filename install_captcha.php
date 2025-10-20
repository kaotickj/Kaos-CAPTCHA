<?php
// install_captcha.php
// Run this once per site/deployment to generate a site-specific captcha.php
// USAGE (CLI): php install_captcha.php /path/to/target/dir
// or configure deploy UI to run it server-side.

if (PHP_SAPI !== 'cli') {
    echo "Please run from command line.\n";
    exit(1);
}

if ($argc < 2) {
    echo "Usage: php install_captcha.php /path/to/target/dir\n";
    exit(1);
}

$targetDir = rtrim($argv[1], "/\\") . DIRECTORY_SEPARATOR;
if (!is_dir($targetDir) || !is_writable($targetDir)) {
    echo "Target directory not found or not writable: $targetDir\n";
    exit(1);
}

// ---- Configuration: ranges and candidate fonts (operators should supply production fonts locally) ----
$publicFonts = [
    'DejaVuSans-Bold.ttf',
    'LiberationSans-Bold.ttf',
    'FreeSans.ttf'
];

// The installer will prefer any TTFs present in $targetDir . 'fonts/', otherwise fall back to publicFonts
$fontDir = $targetDir . 'fonts' . DIRECTORY_SEPARATOR;
$availableFonts = [];
if (is_dir($fontDir)) {
    foreach (glob($fontDir . '*.ttf') as $f) {
        $availableFonts[] = basename($f);
    }
}
if (count($availableFonts) < 8) {
    // pad with public fonts (demo-friendly)
    $availableFonts = array_merge($availableFonts, $publicFonts);
}

// Shuffle and pick 8 fonts (duplicates ok if insufficient)
shuffle($availableFonts);
$fontsSelected = array_slice($availableFonts, 0, 8);
while (count($fontsSelected) < 8) $fontsSelected[] = $publicFonts[array_rand($publicFonts)];

// Parameter ranges (randomized per install)
$params = [
    'width' => 260,
    'height' => 90,
    'digits' => 5,
    'font_size' => rand(28, 36),                    // base font size
    'rotation_min' => -rand(20,30),
    'rotation_max' => rand(20,30),
    'spacing_range' => rand(6,14),                 // px added to base x step
    'y_jitter' => rand(6,12),                      // vertical jitter range
    'noise_lines_min' => rand(12,20),
    'noise_lines_max' => rand(16,24),
    'noise_alpha' => rand(60,255),                 // opacity mapping (used if alpha supported)
    'color_brightness_min' => rand(80,140),        // avoid too-dark or too-light ranges if desired
    'line_color_brightness_min' => rand(100,200),
    'font_list' => $fontsSelected
];

// small sanity adjustments
if ($params['rotation_min'] > -30) $params['rotation_min'] = -30;
if ($params['rotation_max'] < 30) $params['rotation_max'] = 30;

// Build the captcha.php content (template). This file uses $CAPTCHA_PEPPER if present on the server.
// The generated file is self-contained (aside from fonts and background image)
$captchaPhp = "<?php\n";
$captchaPhp .= "session_start();\n\n";
$captchaPhp .= "// Auto-generated site-specific captcha. Do not commit to public repo.\n";
$captchaPhp .= "// If set in environment, CAPTCHA_PEPPER will be used to influence randomness.\n\n";
$captchaPhp .= "\$CAPTCHA_PEPPER = getenv('CAPTCHA_PEPPER') ?: null;\n\n";
$captchaPhp .= "function seeded_bytes(\$len=32) {\n";
$captchaPhp .= "    global \$CAPTCHA_PEPPER;\n";
$captchaPhp .= "    if (\$CAPTCHA_PEPPER) {\n";
$captchaPhp .= "        // create an HMAC using session id and timestamp to produce deterministic-ish bytes per session\n";
$captchaPhp .= "        \$payload = session_id() . '|' . time();\n";
$captchaPhp .= "        \$h = hash_hmac('sha256', \$payload, \$CAPTCHA_PEPPER, true);\n";
$captchaPhp .= "        \$out = \$h;\n";
$captchaPhp .= "        while (strlen(\$out) < \$len) \$out .= hash_hmac('sha256', \$out, \$CAPTCHA_PEPPER, true);\n";
$captchaPhp .= "        return substr(\$out, 0, \$len);\n";
$captchaPhp .= "    }\n";
$captchaPhp .= "    // fallback non-deterministic bytes for demo\n";
$captchaPhp .= "    return random_bytes(\$len);\n";
$captchaPhp .= "}\n\n";

// embed the selected parameters as a PHP array literal
$captchaPhp .= "\$params = " . var_export($params, true) . ";\n\n";

// start of main image generation
$captchaPhp .= <<< 'PHP_BODY'
$width = $params['width'];
$height = $params['height'];
$captchaImage = imagecreatetruecolor($width, $height);

// load background
$backgroundImagePath = 'white.jpg';
if (file_exists($backgroundImagePath)) {
    $backgroundImage = imagecreatefromjpeg($backgroundImagePath);
    imagecopyresized($captchaImage, $backgroundImage, 0, 0, 0, 0, $width, $height, imagesx($backgroundImage), imagesy($backgroundImage));
} else {
    // plain white background fallback
    $white = imagecolorallocate($captchaImage, 255,255,255);
    imagefilledrectangle($captchaImage, 0, 0, $width, $height, $white);
}

// generate digits
$digitsCount = $params['digits'];
$randomDigits = '';
for ($i = 0; $i < $digitsCount; $i++) {
    $randomDigits .= mt_rand(0,9);
}
$_SESSION['captcha'] = $randomDigits;

// select fonts by rotating the provided list
$fontsList = $params['font_list'];
// ensure fonts exist; fallback to any available in same directory
foreach ($fontsList as $k => $f) {
    if (!file_exists(__DIR__ . '/fonts/' . $f) && !file_exists(__DIR__ . '/' . $f)) {
        // leave name as-is; imagettftext will error if not present — operator must ensure fonts exist
    }
}

$textX = 20;
$spacingRange = $params['spacing_range'];
$baseSize = $params['font_size'];

// create seeded bytes for per-request/ session determinism when pepper exists
$seed = seeded_bytes(32);
$seedIdx = 0;

foreach (str_split($randomDigits) as $digit) {
    // derive pseudo-random values from seed bytes to avoid exposing mt_rand distribution
    $b1 = ord($seed[$seedIdx++ % strlen($seed)]);
    $b2 = ord($seed[$seedIdx++ % strlen($seed)]);
    $b3 = ord($seed[$seedIdx++ % strlen($seed)]);
    $rotRange = $params['rotation_max'] - $params['rotation_min'] + 1;
    $rotation = $params['rotation_min'] + ($b1 % $rotRange);
    $textY = intval($height / 2 + ((ord($seed[$seedIdx++ % strlen($seed)]) % ($params['y_jitter'] * 2)) - $params['y_jitter']));

    // color constrained to avoid invisible extremes: use brightness min
    $minC = $params['color_brightness_min'];
    $r = $minC + ($b2 % (256 - $minC));
    $g = $minC + ($b3 % (256 - $minC));
    $b = $minC + (ord($seed[$seedIdx++ % strlen($seed)]) % (256 - $minC));
    $textColor = imagecolorallocate($captchaImage, $r, $g, $b);

    $font = $fontsList[array_rand($fontsList)];
    imagettftext($captchaImage, $baseSize, $rotation, $textX, $textY, $textColor, $font, $digit);

    $textX += 30 + (ord($seed[$seedIdx++ % strlen($seed)]) % ($spacingRange + 1));
}

// add noise lines
$lines = rand($params['noise_lines_min'], $params['noise_lines_max']);
for ($i = 0; $i < $lines; $i++) {
    $sr = $params['line_color_brightness_min'];
    $r = $sr + (ord($seed[$seedIdx++ % strlen($seed)]) % (256 - $sr));
    $g = $sr + (ord($seed[$seedIdx++ % strlen($seed)]) % (256 - $sr));
    $b = $sr + (ord($seed[$seedIdx++ % strlen($seed)]) % (256 - $sr));
    $noiseColor = imagecolorallocate($captchaImage, $r, $g, $b);
    $startX = ord($seed[$seedIdx++ % strlen($seed)]) % $width;
    $startY = ord($seed[$seedIdx++ % strlen($seed)]) % $height;
    $endX = ord($seed[$seedIdx++ % strlen($seed)]) % $width;
    $endY = ord($seed[$seedIdx++ % strlen($seed)]) % $height;
    imageline($captchaImage, $startX, $startY, $endX, $endY, $noiseColor);
}

// output
header('Content-Type: image/png');
imagepng($captchaImage);
imagedestroy($captchaImage);

PHP_BODY;

$captchaPhp .= "\n?>\n";

// write file
$outFile = $targetDir . 'captcha.php';
if (file_put_contents($outFile, $captchaPhp) === false) {
    echo "Failed to write $outFile\n";
    exit(1);
}
echo "Generated $outFile with randomized parameters.\n";
echo "Fonts used (update your 'fonts/' folder with these names if needed):\n";
foreach ($fontsSelected as $f) echo " - $f\n";

exit(0);
