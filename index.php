<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));
$path = end($segments);
if (!preg_match('/^[a-zA-Z0-9_-]*$/', $path)) {
  http_response_code(400);
  die('Ungültiger Kontakt angegeben');
}
$json_file = ($path == '') ? 'index.json' : ltrim($path, '/') . '.json';

if (!file_exists($json_file)) {
  http_response_code(404);
  die('Person nicht gefunden');
}
$json_data = json_decode(file_get_contents($json_file), true);
if (!$json_data) {
  http_response_code(500);
  die('Die gespeicherten Daten sind fehlerhaft');
}

$digital_card_url = "https://{$_SERVER['HTTP_HOST']}/$path";

$profile = $json_data['profile'] ?? [];
$contact = $json_data['contact'] ?? [];
$about = $json_data['about'] ?? '';
$links = $json_data['links'] ?? [];
$files = $json_data['files'] ?? [];
$legal = $json_data['legal'] ?? [];

$name = $profile['firstname'] . ' ' . $profile['lastname'];
if (!empty($profile['middlename'])) {
  $name = $profile['firstname'] . ' ' . $profile['middlename'] . ' ' . $profile['lastname'];
}
if (!empty($profile['title'])) {
  $name = $profile['title'] . ' ' . $name;
}
if (!empty($profile['suffix'])) {
  $name .= ' ' . $profile['suffix'];
}

$action = $_GET['action'] ?? '';

if ($action == 'email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  goto generate_email;
} elseif ($action == 'vcf') {
  goto generate_vcf;
} elseif ($action == 'qr') {
  goto generate_qr;
} else {
  goto generate_html;
}

generate_vcf:
  $filename = preg_replace('/[^\p{L}\p{N}.-]+/u', ' ', $name);
  $filename = trim($filename, ' .');
  $filename = preg_replace('/\s+/u', ' ', trim($filename));
  header('Content-Type: text/vcard');
  header('Content-Disposition: attachment; filename="' . str_replace('+', '%20', urlencode($filename)) . '.vcf"');
  $first_name = $profile['firstname'] ?? '';
  $last_name = $profile['lastname'] ?? '';
  $middle_name = $profile['middlename'] ?? '';
  $title = $profile['title'] ?? '';
  $suffix = $profile['suffix'] ?? '';
  echo "BEGIN:VCARD\n";
  echo "VERSION:3.0\n";
  echo "N:$last_name;$first_name;$middle_name;$title;$suffix\n";
  echo "FN:$name\n";
  if (!empty($profile['department'])) {
    echo "ORG:{$profile['company']};{$profile['department']}\n";
  } else {
    echo "ORG:{$profile['company']}\n";
  }
  if (!empty($profile['position'])) {
    echo "TITLE:{$profile['position']}\n";
  }
  if (!empty($profile['profile_picture'])) {
    $image_data = @file_get_contents($profile['profile_picture']);
    $ext = strtolower(pathinfo($profile['profile_picture'], PATHINFO_EXTENSION));
    $image_type = ($ext === 'jpg' || $ext === 'jpeg') ? 'JPEG' : 'PNG';
    if ($image_data !== false) {
      $base64_image = base64_encode($image_data);
      echo "PHOTO;TYPE={$image_type};ENCODING=b:{$base64_image}\n";
    }
  }
  if (!empty($profile['logo'])) {
    $image_data = @file_get_contents($profile['logo']);
    $ext = strtolower(pathinfo($profile['profile_picture'], PATHINFO_EXTENSION));
    $image_type = ($ext === 'jpg' || $ext === 'jpeg') ? 'JPEG' : 'PNG';
    if ($image_data !== false) {
      $base64_image = base64_encode($image_data);
      echo "LOGO;TYPE={$image_type};ENCODING=b:{$base64_image}\n";
    }
  }
  if (!empty($contact['phone']) && is_array($contact['phone'])) {
    foreach ($contact['phone'] as $phone) {
      $number = $phone['number'] ?? '';
      $type = $phone['type'] ?? 'WORK,VOICE';
      if ($number) {
        echo "TEL;TYPE=$type:$number\n";
      }
    }
  }
  if (!empty($contact['email']) && !($profile['hidemail'] ?? false)) {
    foreach ($contact['email'] as $email) {
      $address = $email['address'] ?? '';
      $type = $email['type'] ?? 'WORK';
      if ($address) {
        echo "EMAIL;TYPE=$type:$address\n";
      }
    }
  }
  if (!empty($contact['address'])) {
    foreach ($contact['address'] as $address) {
      $type = $address['type'] ?? 'WORK';
      $adrname = $address['name'] ?? '';
      $street = $address['street'] ?? '';
      $street2 = $address['street2'] ?? '';
      $city = $address['city'] ?? '';
      $region = $address['region'] ?? '';
      $zip = $address['zip'] ?? '';
      $country = $address['country'] ?? '';
      echo "ADR;TYPE=$type:$adrname;$street2;$street;$city;$region;$zip;$country\n";
    }
  }
  echo "SOURCE:{$digital_card_url}\n";
  echo "URL;TYPE=\"Digitale Visitenkarte\":{$digital_card_url}\n";
  if (!empty($contact['website'])) {
    echo "URL;TYPE=\"Homepage\":{$contact['website']}\n";
  }
  if (!empty($profile['bday'])) {
    $bday = date('Y-m-d', strtotime($profile['bday']));
    echo "BDAY:{$bday}\n";
  }
  $today = date('d.m.Y');
  echo "NOTE:Kennengelernt am {$today}.\n";
  echo "END:VCARD\n";
  exit;

generate_qr:
  require_once 'qrcode.php';
  $qr = QRCode::getMinimumQRCode($digital_card_url, QR_ERROR_CORRECT_LEVEL_L);
  $im = $qr->createImage(16, 16);
  header('Content-Type: image/png');
  imagetruecolortopalette($im, false, 2);
  imagepng($im);
  imagedestroy($im);
  exit;

generate_email:
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$email = $_POST['email'] ?? '';
$privacy = isset($_POST['privacy']);

if (empty($first_name) || empty($last_name) || empty($email) || !$privacy || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  die('Ungültige Eingaben');
}

$to = reset($contact['email'])['address'] ?? '';
if (empty($to)) {
  http_response_code(400);
  die('E-Mail-Adresse nicht verfügbar');
}
$subject = 'Eingehende Kontaktanfrage von ' . htmlspecialchars($first_name . ' ' . $last_name);
$message = nl2br(htmlspecialchars("Name: {$first_name} {$last_name}\nE-Mail: {$email}\n\nDiese Nachricht wurde über die digitale Visitenkarte gesendet."));
$headers = [
  'From: noreply@8bj.de',
  'Reply-To: ' . $email,
  'Content-Type: text/html; charset=UTF-8'
];

require_once 'lib/PHPMailer/PHPMailer.php';
require_once 'lib/PHPMailer/SMTP.php';
require_once 'lib/PHPMailer/Exception.php';
$mailer = new PHPMailer\PHPMailer\PHPMailer(true);
try {
  $mailer->isSMTP();
  $mailer->Host = 'localhost';
  $mailer->Port = 25;
  $mailer->SMTPAuth = false;
  $mailer->SMTPSecure = '';
  $mailer->CharSet = 'UTF-8';

  $mailer->setFrom('noreply@8bj.de', 'Digitale Visitenkarte');
  $mailer->addAddress($to);
  $mailer->Subject = $subject;

  $mailer->isHTML(true);
  $mailer->Body = "<html><body><p style=\"font-family: Arial, sans-serif; font-size: 14px;\">$message</p></body></html>";

  $mailer->send();
  http_response_code(200);
  die();
} catch (Exception $e) {
  http_response_code(500);
  die('Fehler beim Senden der E-Mail: ' . $mailer->ErrorInfo);
}

generate_html:
$color_primary = $profile['primary'] ?? 'rgb(59, 89, 152)';
$color_icons = $profile['icons'] ?? 'rgb(85, 89, 94)';
$color_links = $profile['links'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Digitale Visitenkarte - <?php echo htmlspecialchars($name); ?></title>
  <link rel="icon" href="/favicon.png" type="image/png">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <script src="https://kit.fontawesome.com/07322e29e9.js" crossorigin="anonymous"></script>
  <style type="text/css">
    body {
      color: rgb(85, 89, 94);
    }
    .grid i {
        color: <?php echo htmlspecialchars($color_icons) ?>;
    }
    <?php if (!empty($color_links)): ?>
    a.text-blue-800 {
        color: <?php echo htmlspecialchars($color_links) ?>;
    }
    <?php endif; ?>
    .section {
      border: 1px solid rgb(232, 232, 242);
      background-color: white;
      border-radius: 8px;
    }
    .file-button {
      background-color: rgb(76, 113, 156);
    }
    .file-button:hover {
      background-color: rgb(66, 103, 146);
    }
  </style>
</head>
<body class="bg-gray-50">
  <?php if (!empty($profile['banner'])): ?>
    <div class="w-full h-64 bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($profile['banner']); ?>');"></div>
  <?php endif; ?>
  <div class="max-w-[56rem] mx-auto p-4 bg-white border border-gray-200">
    <div class="section relative p-4 md:pl-8 md:pr-8" style="margin-top: -4rem;">
      <img src="<?php echo htmlspecialchars($profile['profile_picture']); ?>" class="rounded-full" style="width: 10.5rem; height: 10.5rem; border: 5px solid white; position: relative; top: -6.85rem; margin-bottom: -6.75rem;" alt="Profilbild">
      <div class="absolute top-0 right-0 pr-4 md:pr-8">
        <img src="<?php echo htmlspecialchars($profile['logo']); ?>" class="h-16 mt-2" alt="Firmenlogo">
      </div>
      <div class="pl-4" style="border-left: 2px dashed rgb(73, 84, 116);">
        <h1 class="text-2xl"><?php echo htmlspecialchars($name); ?></h1>
        <p><?php echo htmlspecialchars($profile['position']); ?> <?php echo !empty($profile['department']) ? ' – ' . htmlspecialchars($profile['department']) . ' ' : ''; ?>bei <?php echo htmlspecialchars($profile['company']); ?></p>
      </div>
    </div>

    <div class="mt-4">
      <a href="?action=vcf" class="flex items-center w-full text-white px-4 py-2 rounded-full" style="justify-content: space-between; background-color: <?php echo htmlspecialchars($color_primary); ?>">
        <i class="fa-solid fa-download mr-2"></i>
        <span class="flex-grow text-center">Kontakt speichern</span>
      </a>
    </div>

    <?php if ($about): ?>
      <div class="section mt-8 p-4 md:pl-8 md:pr-8">
        <h2 class="text-xl pb-4">Über mich</h2>
        <p><?php echo nl2br(htmlspecialchars($about)); ?></p>
      </div>
    <?php endif; ?>

    <div class="section mt-8 p-4 md:pl-8 md:pr-8">
      <h2 class="text-xl pb-4">Meine Kontaktdaten</h2>
      <div class="grid grid-cols-1 md:grid-cols-2">
        <?php
          function getDisplayType(string $type): string
          {
            if (preg_match('/X-(\w+)/i', $type, $matches)) {
              $display_type = $matches[1];
            } elseif (stripos($type, 'HOME') !== false) {
              $display_type = 'Privat';
            } elseif (stripos($type, 'CELL') !== false) {
              $display_type = 'Mobil';
            } elseif (stripos($type, 'WORK') !== false) {
              $display_type = 'Arbeit';
            } else {
              $display_type = $type;
            }
            return $display_type;
          }

          if (!empty($contact['phone']) && is_array($contact['phone'])):
        ?>
          <?php foreach ($contact['phone'] as $phone): ?>
            <?php
              $number = $phone['number'] ?? '';
              $type = $phone['type'] ?? 'Telefon';
              $display_type = getDisplayType($type);
              $href = 'tel:' . urlencode($number);
              if ($display_type === 'WhatsApp') {
                $clean_number = preg_replace('/[^0-9]/', '', $number);
                $href = 'https://wa.me/' . $clean_number;
              }
            ?>
            <a class="flex items-center p-4 hover:bg-gray-100 hover:underline" href="<?php echo $href; ?>" target="_blank"><i class="fa-solid fa-phone mr-5 flex-shrink-0"></i><span><?php echo htmlspecialchars($number); ?><?php if (count($contact['phone']) > 1): ?><span class="text-gray-400 pl-1">(<?php echo htmlspecialchars($display_type); ?>)</span><?php endif; ?></span></a>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($contact['email']) && !($profile['hidemail'] ?? false)): ?>
          <?php foreach ($contact['email'] as $email): ?>
            <?php
              $address = $email['address'] ?? '';
              $type = $email['type'] ?? 'WORK';
              $display_type = getDisplayType($type);
              $href = 'mailto:' . urlencode($address);
            ?>
            <a class="flex items-center p-4 hover:bg-gray-100 hover:underline" href="<?php echo $href; ?>" target="_blank"><i class="fa-solid fa-envelope mr-5 flex-shrink-0"></i><span><?php echo htmlspecialchars($address); ?><?php if (count($contact['email']) > 1): ?><span class="text-gray-400 pl-1">(<?php echo htmlspecialchars($display_type); ?>)</span><?php endif; ?></span></a>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($contact['address'])): ?>
          <?php foreach ($contact['address'] as $address): ?>
            <?php
              $type = $address['type'] ?? 'WORK';
              $display_type = getDisplayType($type);
              $href = "{$address['street']}, {$address['zip']} {$address['city']}";
              $label = htmlspecialchars($address['street'] . ', ' . $address['zip'] . ' ' . $address['city']);
              if (!empty($address['region'])) {
                $label .= ', ' . htmlspecialchars($address['region']);
                $href .= " {$address['region']}";
              }
              if (!empty($address['country'])) {
                if ($address['country'] !== 'Germany') {
                  $label .= ', ' . htmlspecialchars($address['country']);
                }
                $href .= " {$address['country']}";
              }
              if (!empty($address['street2'])) {
                $label = htmlspecialchars($address['street2']) . ', ' . $label;
                $href = "{$address['street2']}, $href";
              }
              if (!empty($address['name'])) {
                if ($address['name'] !== $profile['company']) {
                  $label = htmlspecialchars($address['name']) . ', ' . $label;
                }
                $href = "{$address['name']}, $href";
              }
              $href = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($href);
            ?>
            <a class="flex items-center p-4 hover:bg-gray-100 hover:underline" href="<?php echo $href; ?>" target="_blank"><i class="fa-solid fa-location-dot mr-5 flex-shrink-0"></i>
              <span><?php echo str_replace(', ', '<br>', $label); ?><?php if (count($contact['address']) > 1): ?><span class="text-gray-400 pl-1">(<?php echo htmlspecialchars($display_type); ?>)</span><?php endif; ?></span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($contact['website'])): ?>
          <a class="flex items-center p-4 hover:bg-gray-100 hover:underline" href="<?php echo htmlspecialchars($contact['website']); ?>" target="_blank"><i class="fa-solid fa-globe mr-5 flex-shrink-0"></i><?php echo htmlspecialchars($contact['website']); ?></a>
        <?php endif; ?>
        <?php if (!empty($profile['bday'])): ?>
          <a class="flex items-center p-4 hover:bg-gray-100 hover:underline" href="https://www.google.com/calendar/render?action=TEMPLATE&text=%F0%9F%8E%82%20<?php echo urlencode($name); ?>&recur=RRULE:FREQ=YEARLY&dates=<?php echo date('Ymd', strtotime($profile['bday'])) . '/' . date('Ymd', strtotime($profile['bday'])); ?>" target="_blank"><i class="fa-solid fa-cake-candles mr-5 flex-shrink-0"></i>
            <span><?php echo htmlspecialchars(date('d.m.Y', strtotime($profile['bday']))); ?></span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($links): ?>
      <div class="section mt-8 p-4 md:pl-8 md:pr-8">
        <h2 class="text-xl pb-4">Meine Links</h2>
        <div class="space-y-2">
          <?php foreach ($links as $link): ?>
            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="flex items-center w-full px-4 py-2 rounded-full text-white" style="background: <?php echo htmlspecialchars($link['background'] ?? 'rgb(31, 57, 106)'); ?>; justify-content: space-between;">
              <i class="<?php echo htmlspecialchars($link['icon'] ?? 'fa-solid fa-link'); ?> mr-2"></i>
              <span class="flex-grow text-center"><?php echo htmlspecialchars($link['title']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($files): ?>
      <div class="section mt-8 p-4 md:pl-8 md:pr-8">
        <h2 class="text-xl pb-4">Meine Dateien</h2>
        <div class="space-y-2">
          <?php foreach ($files as $file): ?>
            <a href="<?php echo htmlspecialchars($file['url']); ?>" target="_blank" class="flex items-center w-full px-4 py-2 rounded-full file-button text-white" style="justify-content: space-between;">
              <i class="<?php echo htmlspecialchars($file['icon'] ?? 'fa-solid fa-file'); ?> mr-2"></i>
              <span class="flex-grow text-center"><?php echo htmlspecialchars($file['title']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($contact['email'])): ?>
    <div class="section mt-8 p-4 md:pl-8 md:pr-8">
      <h2 class="text-xl pb-4">Hinterlasse Deinen Kontakt</h2>
      <form id="contact-form" class="mt-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input type="text" name="first_name" placeholder="Vorname *" class="border p-2 rounded w-full" required>
          <input type="text" name="last_name" placeholder="Nachname *" class="border p-2 rounded w-full" required>
        </div>
        <input type="email" name="email" placeholder="E-Mail *" class="border p-2 rounded w-full mt-4" required>
        <div class="mt-4">
          <input type="checkbox" name="privacy" id="privacy" class="mr-2" required>
          <label for="privacy" class="cursor-pointer">Ich bestätige, dass ich die <a href="<?php echo htmlspecialchars($legal['datenschutz']); ?>" class="text-blue-800 hover:underline">Datenschutzerklärung</a> zur Kenntnis genommen habe und mit der Verarbeitung meiner personenbezogenen Daten durch den Nutzer oder dessen Firma zu den genannten Zwecken einverstanden bin. Ich kann meine Einwilligung jederzeit widerrufen. *</label>
        </div>
        <div id="success-message" class="hidden mt-4 p-2 rounded text-white w-full" style="background-color: rgb(21, 128, 61);">
          <i class="fa-solid fa-circle-check mr-2"></i>Dein Kontakt wurde digital übermittelt!
        </div>
        <div class="mt-4 text-center">
          <button type="submit" class="text-white px-4 py-2 rounded-full" style="min-width: 50%; background-color: <?php echo htmlspecialchars($color_primary); ?>">Absenden</button>
        </div>
      </form>
    </div>

    <script>
      let busy = false;
      document.getElementById('contact-form').addEventListener('submit', async function(event) {
        event.preventDefault();
        if (busy) return;
        busy = true;
        const form = event.target;
        const formData = new FormData(form);
        try {
          const response = await fetch(window.location.pathname + '?action=email', {
            method: 'POST',
            body: formData
          });
          if (response.ok) {
            document.getElementById('success-message').classList.remove('hidden');
          } else {
            busy = false;
            alert('Fehler beim Senden der Nachricht.');
          }
        } catch (error) {
          busy = false;
          alert('Ein Fehler ist aufgetreten: ' + error.message);
        }
      });
    </script>
    <?php endif; ?>

    <div class="mt-8 flex justify-center space-x-4">
      <a href="<?php echo htmlspecialchars($legal['impressum']); ?>" class="text-blue-800 hover:underline">Impressum</a>
      <a href="<?php echo htmlspecialchars($legal['datenschutz']); ?>" class="text-blue-800 hover:underline">Datenschutz</a>
    </div>
  </div>
</body>
</html>
<?php
exit;
?>