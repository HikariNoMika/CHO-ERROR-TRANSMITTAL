# CHO Error Transmittal (MCA)

A Laravel web application for recording PCU/CHO error transmittals and generating the supporting
Excel documents from a clinic-supplied template.

- Store patient records and their images (PhilHealth ID, empanelment error)
- Fill an Excel template with placeholders such as `{{patient_name}}`, `{{birthdate}}` and
  `{{image_with_id}}`
- Generate one document or batch up to 100 records at a time
- Track every generation in history, and audit-log actions per user
- Admin and staff roles; Settings restricted to admins

Full documentation, including the complete placeholder list and an installation walkthrough, is
available in-app at **`/docs`** (public, no login required).

## Requirements

- PHP **8.2+**
- [Composer](https://getcomposer.org/)
- Node.js / npm — *optional*, see the note below

## Installation

```bash
git clone https://github.com/HikariNoMika/CHO-ERROR-TRANSMITTAL.git
cd CHO-ERROR-TRANSMITTAL

composer install

# Windows
copy .env.example .env
# macOS / Linux
cp .env.example .env

php artisan key:generate

# The SQLite database is git-ignored, so create the empty file first.
# Windows PowerShell
New-Item database\database.sqlite
# macOS / Linux
touch database/database.sqlite

php artisan migrate
php artisan db:seed

php artisan serve
```

Then open <http://127.0.0.1:8000>.

To update an existing clone:

```bash
git pull
composer install
php artisan migrate
```

### Default accounts — change these immediately

`php artisan db:seed` creates two users. **These credentials are public in this repository, so
change the passwords before putting the app anywhere reachable.**

| Email | Password | Role |
| --- | --- | --- |
| `admin@example.com` | `password` | Admin (full access, including Settings) |
| `staff@example.com` | `password` | Staff (records and documents only) |

There is no user-management screen yet, so set new passwords from the command line:

```bash
php artisan tinker
>>> App\Models\User::where('email', 'admin@example.com')->first()
      ->update(['password' => bcrypt('your-new-password')]);
```

Add more users the same way, with `'role' => 'staff'` or `'role' => 'admin'`. Set
`'is_active' => false` to disable an account without losing its history.

### Node.js is optional

The application layout ships its CSS inline and does not load Vite assets, so `npm install` and
`npm run build` are **not** required to run the app. They only matter if you intend to change
`resources/css/app.css` or `resources/js/app.js`.

## First run

1. Sign in as the admin and open **Settings**.
2. Set your **facility name** and **facility address** — these feed the `{{facility_name}}` and
   `{{facility_address}}` placeholders in every generated document.
3. Upload your Excel template. It becomes the active template, its version is bumped, and its
   placeholders are detected for you.
4. Cross-check the detected placeholders against the table in `/docs`.

## Template placeholders at a glance

| Placeholder | Fills |
| --- | --- |
| `{{patient_name}}` (also `fullname`, `full_name`, `person_fullname`, `name`, `beneficiary_name`) | Patient name |
| `{{birthdate}}` (also `person_bdate`, `bdate`) | Birthdate, as `MM-DD-YYYY` |
| `{{philhealth_id}}` (also `person_philid`, `philid`) | PhilHealth ID |
| `{{head_of_clinic}}` (also `office_head`) | Clinic head |
| `{{date_today}}` | Generation date |
| `{{facility_name}}` / `{{facility_address}}` | From Settings |
| `{{appointment_date}}` (also `date_of_appointment`) | Appointment date |
| `{{auth_transaction_code}}` (also `auth_code`, `atc`, `atc_code`) | ATC |
| `{{pcu_error_code}}` (also `pcu_code`, `pcu_error`) | PCU error code |
| `{{image_with_id}}` or `{{person_with_id}}` | ID image, anchored over the cell |
| `{{empanelment_error}}` | Empanelment error image, anchored over the cell |

Notes:

- Placeholders are case-insensitive and can appear inside surrounding text
  (`Patient: {{fullname}}`).
- An unrecognised placeholder is **left in the output as typed**, never blanked — so a stray
  `{{...}}` in a generated file means the name is not in the table.
- Image placeholders place the image over the cell and clear the text. Only the **first** matching
  cell on each sheet receives the image.
- See `/docs` for the authoritative list.

## Record lifecycle

`draft` → `generated` → `printed`

- A record stays `draft` until a document is generated for it.
- Generating sets `generated` and stores the file in history.
- `printed` is set manually from the record page and is one-way.
- Re-generating a record is allowed and appends a new generation; earlier files stay downloadable.

## Security notes

`.env`, the SQLite database, uploaded images and generated documents are all git-ignored and must
never be committed — they can contain patient information. `storage/app/private` and
`database/*.sqlite*` are covered by `.gitignore` for this reason.

## License

MIT. Built with [Laravel](https://laravel.com),
[PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/) and
[Laravel Tinker](https://github.com/laravel/tinker).