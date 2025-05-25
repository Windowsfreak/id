# Digital Business Card for id.8bj.de

## Overview
This project implements a responsive digital business card hosted at `id.8bj.de`, designed to display personalized contact information, social links, and files. It supports dynamic loading of profiles based on URL paths (e.g., `id.8bj.de/pikachu`), vCard (VCF) generation, QR code creation, and a contact form for user submissions. The design features a clean, modern layout with Tailwind CSS and Font Awesome icons.

## Features
- **Dynamic Profiles**: Loads profile data from JSON files based on URL paths (e.g., `pikachu.json` for `id.8bj.de/pikachu`).
- **Responsive Design**: Uses Tailwind CSS for a mobile-friendly layout with a centered container, a full-width banner, and card-like sections.
- **Contact Information**: Displays multiple phone numbers and addresses with types (e.g., "Privat", "Mobil", "WhatsApp") and links (`tel:`, `https://wa.me/`, Google Maps, Google Calendar).
- **vCard Generation**: Generates a VCF file (`?action=vcf`) with name, organization, contact details, profile picture, logo, title, and source URL.
- **QR Code**: Generates a QR code (`?action=qr`) linking to the current profile URL.
- **Contact Form**: Allows users to submit their contact details via a POST request (`?action=email`), sent via a local SMTP server using PHPMailer.
- **Styling**: Buttons, logos and primary colours can be customised for each profile.

## Prerequisites
- **Server**: A web server (e.g., Caddy) with PHP support (PHP-FPM).
- **PHP**: Version 8.4 or higher with `allow_url_fopen` enabled for image fetching.
- **SMTP Server**: Local SMTP server on `localhost:25` without authentication (e.g., Postfix).
- **Dependencies**:
  - [Tailwind CSS](https://tailwindcss.com/)
  - [Font Awesome](https://fontawesome.com) (requires a kit ID)
  - [PHPMailer](https://github.com/PHPMailer/PHPMailer) (for email sending)
  - [qrcode.php](https://github.com/kazuhikoarase/qrcode-generator) (for QR code generation)

## Installation
1. **Clone or Set Up Repository**:
   ```bash
   git clone https://github.com/Windowsfreak/id.git /var/www/html
   ```
   Or manually create `/var/www/html` and copy files.

2. **Install PHPMailer**:
   Download PHPMailer or install via Composer:
   ```bash
   composer require phpmailer/phpmailer
   ```
   Place `PHPMailer.php`, `SMTP.php`, and `Exception.php` in `/var/www/html/lib/PHPMailer`.

3. **Install QR Code Library**:
   Download `qrcode.php` from [kazuhikoarase/qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) and place it in `/var/www/html`.

4. **Configure Caddy**:
   Create a `Caddyfile` in your Caddy configuration directory:
   ```
   id.8bj.de {
       root * /var/www/id
       php_fastcgi unix//run/php/php-fpm.sock
       file_server
   }
   ```
   Restart Caddy to apply changes.

5. **Create JSON Files**:
   Place profile JSON files (e.g., `pikachu.json`, `index.json`) in `/var/www/id`. Example structure:
   ```json
   {
     "profile": {
       "firstname": "Björn",
       "lastname": "Eberhardt",
       "company": "Thoughtworks Deutschland GmbH",
       "department": "Professional Services",
       "position": "Senior Consultant Developer",
       "profile_picture": "https://example.com/profile.jpg",
       "banner": "https://example.com/banner.jpg",
       "logo": "https://example.com/logo.png",
       "bday": "1989-12-16",
       "primary": "rgb(0, 61, 79)",
       "icons": "rgb(242, 97, 122)",
       "links": "rgb(242, 97, 122)",
       "hidemail": true
     },
     "contact": {
       "phones": [
         {"number": "+4940386870830", "type": "WORK,VOICE,PREF"},
         {"number": "+491741234567", "type": "WhatsApp"}
       ],
       "email": [
         {"number": "info@example.com", "type": "WORK,PREF"}
       ],
       "address": [
         {
           "type": "WORK",
           "street": "Caffamacherreihe 7",
           "city": "Hamburg",
           "zip": "20355",
           "country": "Germany"
         }
       ],
       "website": "https://www.thoughtworks.com/de-de"
     },
     "about": "Consultant Developer with over 10 years of experience.",
     "links": [
       {
         "title": "LinkedIn",
         "icon": "fa-brands fa-linkedin",
         "url": "https://linkedin.com/in/bjoerneberhardt",
         "background": "rgb(11, 102, 194)"
       }
     ],
     "files": [
       {
         "title": "Company Brochure",
         "icon": "fa-solid fa-file-pdf",
         "url": "https://example.com/brochure.pdf"
       }
     ],
     "legal": {
       "impressum": "https://www.thoughtworks.com/de-de/about-us/impressum",
       "datenschutz": "https://www.thoughtworks.com/de-de/about-us/privacy-policy"
     }
   }
   ```

6. **Set Up SMTP**:
   Ensure a local SMTP server (e.g., Postfix) is running on `localhost:25` without authentication.

7. **Update Font Awesome Kit**:
   Replace `your-kit-id.js` in `index.php` with your Font Awesome Kit ID.

## File Structure
```
/var/www/id
├── index.php          # Main PHP script handling HTML, VCF, QR, and email
├── qrcode.php         # QR code generation library
├── lib/
│   └── PHPMailer/     # PHPMailer library files
├── pikachu.json       # Example profile JSON
└── index.json         # Default profile JSON for root URL
```

## Usage
- **View Profile**: Access `id.8bj.de/<path>` (e.g., `id.8bj.de/pikachu`) to display the digital business card.
- **Download vCard**: Append `?action=vcf` (e.g., `id.8bj.de/pikachu?action=vcf`) to download a VCF file with contact details, including base64-encoded profile picture and logo.
- **Generate QR Code**: Append `?action=qr` to generate a QR code linking to the profile URL.
- **Submit Contact**: Use the contact form to send details to the first Email address (i.e. `info@example.com`) via `?action=email` (POST request).

## Security Notes
- **Path Validation**: URL paths are sanitized to allow only alphanumeric characters, hyphens, and underscores, preventing path traversal.
- **Input Escaping**: All user inputs and JSON data are escaped with `htmlspecialchars` to prevent XSS.
  Note that it is still possible to inject HTML, CSS and other code into the JSON files, so ensure that the JSON data is trusted.
- **Email Validation**: Form submissions are validated (`filter_var` for emails) to ensure data integrity.

## Development
- **JSON Extension**: Add fields to JSON files (e.g., `X-GENDER`) and update `index.php` for vCard support.
- **Email Customization**: Modify the HTML email template in the `generate_email` block of `index.php`.

## License
Creative Commons Zero (CC0) - Public Domain Dedication. This project is free to use, modify, and distribute without restrictions.

## Contact
For issues or enhancements, contact me.