@extends('layouts.app')

@section('page-title')
    <h2 class="page-title">Documentation</h2>
@endsection

@section('content')
<div class="docs">

    <nav class="docs-nav" aria-label="Sections">
        <a href="#documentation">Documentation</a>
        <a href="#installation">Installation</a>
        <a href="#guide">User Guide</a>
        <a href="#credits">Credits</a>
    </nav>

    {{-- ============================ DOCUMENTATION ============================ --}}
    <section id="documentation">
        <h2>Documentation</h2>

        <p>
            MCA Patient Docs manages <strong>PCU (PhilHealth Claims Validation)</strong> patient records
            and turns them into Excel workbooks for claims submission. Records are handled in two
            separate lists &mdash; <strong>PCU Error Records</strong> and <strong>PCU Success
            Records</strong> &mdash; because a success record only ever carries four log fields, while
            an error record carries the full clinical and image detail.
        </p>

        <h3>Record lifecycle</h3>
        <table>
            <thead>
                <tr><th>Status</th><th>Meaning</th><th>Actions in the list</th><th>Actions on the record page</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Draft</strong></td>
                    <td>Captured, but no document has been produced yet.</td>
                    <td>View, Edit, Delete</td>
                    <td>Generate &amp; Print, Edit</td>
                </tr>
                <tr>
                    <td><strong>Generated</strong></td>
                    <td>An Excel workbook exists for this record.</td>
                    <td>View, Edit, Print, Delete</td>
                    <td>Print, Download Excel, Mark as Printed, Edit</td>
                </tr>
                <tr>
                    <td><strong>Printed</strong></td>
                    <td>A staff member has marked the document as printed and submitted.</td>
                    <td>View, Edit, Print, Delete</td>
                    <td>Print, Download Excel, Edit</td>
                </tr>
            </tbody>
        </table>

        <p>
            The list deliberately offers only a short set of actions. Open a record to reach the
            full set &mdash; that is where generation, downloading and marking as printed live.
        </p>

        <div class="docs-note">
            <strong>Every generation is kept.</strong> Re-generating a record creates a <em>new</em>
            workbook and a new history entry &mdash; it never overwrites the previous one. Past
            workbooks stay listed under <strong>Generation History</strong> on the record page, with
            the template version and the user who generated it, and each remains downloadable.
        </div>

        <h3>Required fields</h3>
        <p>Error records need, at minimum:</p>
        <ul>
            <li>Patient name, birthdate, PhilHealth ID and PCU error code</li>
            <li>An <strong>ID Image</strong> and an <strong>Empanelment Error Image</strong></li>
            <li>An active Excel template (see <strong>Settings</strong>)</li>
        </ul>
        <p>
            Generation is refused for a record that is missing any of these, and the missing field is
            named back to you. In a bulk run one incomplete record never blocks the others &mdash;
            failures are reported per record.
        </p>
        <p>
            Success records need only name, birthdate, PIN and success code &mdash; no images, no
            template.
        </p>

        <h3>Template placeholders</h3>
        <p>
            Placeholders are typed straight into the cells of your Excel template, wrapped in double
            braces &mdash; for example <code>@{{patient_name}}</code>. At generation the app replaces
            each one with that record's data.
        </p>

        <h4>How they behave</h4>
        <ul>
            <li>Only letters, digits and underscores are recognised, so <code>@{{patient_name}}</code> works and <code>@{{patient name}}</code> does not.</li>
            <li>A placeholder can sit inside surrounding text: <code>Patient: @{{fullname}}</code> is replaced correctly.</li>
            <li>Dates are written as <code>MM-DD-YYYY</code>.</li>
            <li>Names are <em>not</em> case sensitive, and several spellings resolve to the same field, so you can keep an existing template's vocabulary.</li>
            <li>An <strong>unrecognised</strong> placeholder is left in the output exactly as typed &mdash; it is never blanked. If you see <code>@{{something}}</code> in a generated document, that name is not in the table below.</li>
            <li>Text and formatting are preserved: the surrounding cell style is reapplied after substitution.</li>
        </ul>

        <h4>Text placeholders</h4>
        <table>
            <thead>
                <tr><th>Fills</th><th>Any of these names will work</th></tr>
            </thead>
            <tbody>
                <tr><td>Patient name</td><td><code>patient_name</code> <code>fullname</code> <code>full_name</code> <code>person_fullname</code> <code>name</code> <code>beneficiary_name</code></td></tr>
                <tr><td>Birthdate</td><td><code>birthdate</code> <code>person_bdate</code> <code>bdate</code></td></tr>
                <tr><td>PhilHealth ID</td><td><code>philhealth_id</code> <code>person_philid</code> <code>philid</code></td></tr>
                <tr><td>Head of clinic</td><td><code>head_of_clinic</code> <code>office_head</code></td></tr>
                <tr><td>Generation date</td><td><code>date_today</code></td></tr>
                <tr><td>Facility name <span class="hint">(from Settings)</span></td><td><code>facility_name</code> <code>clinic_name</code> <code>health_care_institution</code></td></tr>
                <tr><td>Facility address <span class="hint">(from Settings)</span></td><td><code>facility_address</code> <code>institution_address</code></td></tr>
                <tr><td>Appointment date</td><td><code>appointment_date</code> <code>date_of_appointment</code></td></tr>
                <tr><td>ATC / auth transaction code</td><td><code>auth_transaction_code</code> <code>auth_code</code> <code>atc</code> <code>atc_code</code></td></tr>
                <tr><td>PCU error code</td><td><code>pcu_error_code</code> <code>pcu_code</code> <code>pcu_error</code></td></tr>
            </tbody>
        </table>

        <p>
            <code>patient_name</code>, <code>birthdate</code>, <code>philhealth_id</code> and
            <code>head_of_clinic</code> are treated as required when a template is analysed, so a
            record missing any of them will be refused with the field named.
        </p>

        <h4>Image placeholders</h4>
        <p>
            Image placeholders work differently from text ones: the uploaded picture is placed
            <em>over</em> the cell and the placeholder text is removed. <strong>Only the first
            matching cell on each sheet receives the image</strong>, so use one image placeholder per
            sheet.
        </p>
        <table>
            <thead>
                <tr><th>Slot</th><th>Placeholder to use</th><th>Rendered?</th></tr>
            </thead>
            <tbody>
                <tr><td>ID image</td><td><code>@{{image_with_id}}</code> or <code>@{{person_with_id}}</code></td><td>Yes</td></tr>
                <tr><td>Empanelment error image</td><td><code>@{{empanelment_error}}</code></td><td>Yes</td></tr>
                <tr>
                    <td colspan="3" class="hint">
                        These are also classified as image placeholders when a template is analysed,
                        but are <strong>not</strong> drawn into the document:
                        <code>@{{id_image}}</code>, <code>@{{photo}}</code>,
                        <code>@{{empanelment_error_image}}</code>, <code>@{{error_image}}</code>.
                        Stick to the two names above.
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="docs-note">
            <strong>Adding a placeholder the app does not know?</strong> Add an entry to
            <code>app/Services/PlaceholderMap.php</code> (the <code>TEXT</code> or <code>IMAGES</code>
            constant) mapping it to the canonical field. Do not edit generated documents by hand
            &mdash; the mapping is what keeps generation, validation and template analysis agreeing
            with each other.
        </div>

        <h3>Roles and access</h3>
        <table>
            <thead>
                <tr><th>Role</th><th>Access</th></tr>
            </thead>
            <tbody>
                <tr><td><strong>Admin</strong></td><td>Everything staff can do, plus Settings and templates.</td></tr>
                <tr><td><strong>Staff</strong></td><td>Records, document generation, print and download.</td></tr>
                <tr><td><em>Inactive</em></td><td>Blocked. Deactivating a user revokes access immediately, without deleting their history.</td></tr>
            </tbody>
        </table>

        <h3>Filtering and paging</h3>
        <ul>
            <li><strong>Search</strong> matches patient name, PhilHealth ID or error code.</li>
            <li><strong>Date From / Date To</strong> filter on the date the record was created. Either may be used alone for an open-ended range.</li>
            <li><strong>Rows</strong> sets page size: 10, 25, 50 or 100.</li>
            <li><strong>Export Excel</strong> downloads the current filtered list.</li>
        </ul>
    </section>

    {{-- ============================= INSTALLATION ============================= --}}
    <section id="installation">
        <h2>Installation</h2>

        <p>
            These steps assume you have just pulled or cloned the repository. Everything below runs
            from the project root.
        </p>

        <h3>1. Requirements</h3>
        <ul>
            <li><strong>PHP 8.2 or newer</strong> (the project requires <code>^8.2</code>).</li>
            <li><strong>Composer</strong>.</li>
            <li><strong>Node.js and npm</strong> &mdash; <em>optional</em>, see the note below.</li>
        </ul>
        <div class="docs-note">
            <strong>You can skip Node entirely.</strong> The application layout ships its CSS inline
            and does not load Vite assets, so <code>npm install</code> and <code>npm run build</code>
            are not required to run the app. They only matter if you intend to edit
            <code>resources/css/app.css</code> or <code>resources/js/app.js</code>.
        </div>

        <h3>2. Install PHP dependencies</h3>
        <pre><code>composer install</code></pre>

        <h3>3. Create your environment file</h3>
        <pre><code>copy .env.example .env        <span class="hint"># Windows
cp .env.example .env          # macOS / Linux</span></code></pre>
        <p>
            The repository deliberately does <strong>not</strong> contain a <code>.env</code> &mdash;
            it is git-ignored, because it holds secrets.
        </p>

        <h3>4. Generate the application key</h3>
        <pre><code>php artisan key:generate</code></pre>

        <h3>5. Create the database file</h3>
        <p>
            The default database is SQLite, stored at <code>database/database.sqlite</code>. That file
            is git-ignored too, so it is absent on a fresh clone and must be created before migrating.
        </p>
        <pre><code>touch database/database.sqlite        <span class="hint"># macOS / Linux
New-Item database\database.sqlite  <span class="hint"># Windows PowerShell</span></code></pre>
        <p>
            To use MySQL or PostgreSQL instead, fill in the <code>DB_CONNECTION</code>,
            <code>DB_HOST</code>, <code>DB_DATABASE</code>, <code>DB_USERNAME</code> and
            <code>DB_PASSWORD</code> keys in <code>.env</code> and create that database yourself. The
            commented-out block in <code>.env.example</code> is there for this.
        </p>

        <h3>6. Run the migrations</h3>
        <pre><code>php artisan migrate</code></pre>
        <p>
            This creates every table, including the <code>sessions</code>, <code>cache</code> and
            <code>jobs</code> tables the app relies on &mdash; the default
            <code>.env.example</code> uses database-backed sessions and cache, so this step is not
            optional.
        </p>

        <h3>7. Create the first users</h3>
        <pre><code>php artisan db:seed</code></pre>
        <p>This creates two accounts:</p>
        <table>
            <thead>
                <tr><th>Email</th><th>Password</th><th>Role</th></tr>
            </thead>
            <tbody>
                <tr><td><code>admin@example.com</code></td><td><code>password</code></td><td>Admin &mdash; full access including Settings</td></tr>
                <tr><td><code>staff@example.com</code></td><td><code>password</code></td><td>Staff &mdash; records and documents only</td></tr>
            </tbody>
        </table>

        <div class="docs-note">
            <strong>Change these passwords before going live.</strong> They are published in this
            repository, so anyone who can read the repo knows them. There is currently
            <em>no user-management screen</em>, so set a new password from the command line:
        </div>
        <pre><code>php artisan tinker
>>> App\Models\User::where('email', 'admin@example.com')->first()
      ->update(['password' =&gt; bcrypt('your-new-password')]);</code></pre>
        <p>
            To add further users, duplicate that call with a different email and
            <code>'role' =&gt; 'staff'</code> (or <code>'admin'</code>). Set
            <code>'is_active' =&gt; false</code> to disable an account without deleting its history.
        </p>

        <h3>8. Build front-end assets <span class="hint">(optional)</span></h3>
        <pre><code>npm install
npm run build     <span class="hint"># or: npm run dev</span></code></pre>

        <h3>9. Serve the application</h3>
        <pre><code>php artisan serve</code></pre>
        <p>Then open <code>http://127.0.0.1:8000</code> and sign in.</p>

        <h3>10. First-run configuration</h3>
        <ol>
            <li>Go to <strong>Settings</strong> and set your <strong>facility name</strong> and <strong>facility address</strong>. These feed the <code>facility_name</code> and <code>facility_address</code> placeholders in every generated document.</li>
            <li>Upload your Excel claim template. It becomes the active template, its version is bumped, and its placeholders are detected for you.</li>
            <li>Check the placeholder list matches the table above. Correct any name the analysis does not recognise.</li>
        </ol>

        <h3>11. Uploads and generated files</h3>
        <p>
            Uploaded images and generated workbooks are stored on the <code>private</code> disk at
            <code>storage/app/private</code>. This directory is git-ignored and is created for you;
            make sure the web server can write to <code>storage/</code> and <code>bootstrap/cache/</code>
            in production.
        </p>
        <p>
            If images fail to display on a fresh install, check that those directories are writable
            and that PHP has the <code>gd</code> or <code>imagick</code> extension available.
        </p>

        <h3>Common first-run problems</h3>
        <table>
            <thead>
                <tr><th>Symptom</th><th>Fix</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><em>"Database file at path [...] does not exist"</em></td>
                    <td>You skipped step 5. Create the empty SQLite file.</td>
                </tr>
                <tr>
                    <td><em>500 with a missing APP_KEY</em></td>
                    <td>You skipped step 4. Run <code>php artisan key:generate</code>.</td>
                </tr>
                <tr>
                    <td><em>"No application encryption key"</em></td>
                    <td><code>.env</code> is missing or the key was never generated.</td>
                </tr>
                <tr>
                    <td><em>Login fails / session error</em></td>
                    <td>Migrations were not run, so the <code>sessions</code> table is missing.</td>
                </tr>
                <tr>
                    <td><code>npm run build</code> fails</td>
                    <td>You do not need it &mdash; the app layout does not use Vite. Ignore it.</td>
                </tr>
                <tr>
                    <td>Permission denied writing to <code>storage</code></td>
                    <td>Fix ownership of <code>storage/</code> and <code>bootstrap/cache/</code>.</td>
                </tr>
            </tbody>
        </table>
    </section>

    {{-- ================================ GUIDE ================================ --}}
    <section id="guide">
        <h2>User Guide</h2>

        <h3>Daily workflow</h3>
        <ol>
            <li><strong>Filter first.</strong> Use the date range to narrow to the batch you are processing. A filtered list is far quicker to work through than the full table.</li>
            <li><strong>Select records.</strong> Tick individual checkboxes, or use the header checkbox to select every row on the page. Bulk actions always apply to the page you are looking at.</li>
            <li><strong>Generate.</strong> Press <em>Generate selected</em>. A confirmation tells you exactly how many records will be produced and whether any already-generated documents will be replaced.</li>
            <li><strong>Review the result banner.</strong> It reports how many succeeded and names every failure with its reason.</li>
            <li><strong>Print or download.</strong> Open the record &mdash; the list itself only carries View, Edit, Print and Delete.</li>
        </ol>

        <h3>Bulk generation</h3>
        <p>
            Bulk generation produces <strong>one workbook per record</strong>, not a single combined
            file, so each record stays independently downloadable and auditable.
        </p>
        <ul>
            <li><strong>Current page only.</strong> A selection never spans pages &mdash; generate, then move to the next page.</li>
            <li><strong>Maximum 100 records</strong> per submission.</li>
            <li><strong>Skip already generated</strong> is on by default, and the label shows how many records it is excluding. Turn it off to deliberately re-generate a batch &mdash; for example after a template correction.</li>
            <li><strong>Partial success.</strong> Every record is authorised and validated on its own. One bad record will not fail the batch; it is reported individually.</li>
        </ul>
        <p>A 100-record batch takes roughly 4 seconds, during which the button shows progress so the page is never ambiguous.</p>

        <div class="docs-note">
            <strong>Before re-generating a finished batch:</strong> a new workbook becomes the
            record's current document. The earlier file is preserved in the record's generation
            history and remains downloadable, but staff will download the newest one by default.
        </div>

        <h3>Printing and marking as printed</h3>
        <p>
            Open a record to reach the document actions. A draft offers <em>Generate &amp; Print</em>;
            once a document exists you get <em>Print</em> and <em>Download Excel</em>.
        </p>
        <p>
            <strong>Mark as Printed</strong> appears on the record page only while the record is
            <strong>Generated</strong>. It is a manual flag you set once the document has actually
            been printed and submitted, and it is <strong>one-way</strong> &mdash; a printed record
            cannot be sent back to Generated. Use it to keep the outstanding queue honest rather
            than as an automatic step.
        </p>

        <h3>Managing templates (Settings)</h3>
        <p>
            Settings is admin-only. Uploading a template makes it the active one, bumps its version,
            and detects the placeholders it contains so you can map them to record fields. Only one
            template is active at a time; existing documents keep pointing at the version they were
            generated from.
        </p>

        <h3>Troubleshooting</h3>
        <table>
            <thead>
                <tr><th>Symptom</th><th>Cause and fix</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><em>"missing Empanelment Error Image, ID Image"</em></td>
                    <td>The record has no uploaded image. Edit the record and attach both images.</td>
                </tr>
                <tr>
                    <td><em>"One or more selected records no longer exist."</em></td>
                    <td>The list was out of date &mdash; someone deleted a record after you loaded the page. Refresh and re-select.</td>
                </tr>
                <tr>
                    <td><em>"The records field is required."</em></td>
                    <td>Nothing was selected. Tick at least one checkbox.</td>
                </tr>
                <tr>
                    <td>Sign in fails on an account that used to work</td>
                    <td>The user has been deactivated. An admin must reactivate the account.</td>
                </tr>
                <tr>
                    <td>Download returns <em>not found</em></td>
                    <td>The record has no document yet &mdash; generate it first.</td>
                </tr>
            </tbody>
        </table>
    </section>

    {{-- =============================== CREDITS =============================== --}}
    <section id="credits">
        <h2>Credits</h2>

        <h3>Built with</h3>
        <p>MCA Patient Docs is built on the following open-source packages. All are MIT licensed.</p>
        <ul class="credits-list">
            <li>
                <span class="cr-name">Laravel Framework</span>
                <span class="cr-meta">v12.69.3 &middot; MIT &middot; laravel.com</span>
            </li>
            <li>
                <span class="cr-name">PhpSpreadsheet</span>
                <span class="cr-meta">v5.10.0 &middot; MIT &middot; phpspreadsheet.readthedocs.io</span>
            </li>
            <li>
                <span class="cr-name">Laravel Tinker</span>
                <span class="cr-meta">v2.11.1 &middot; MIT &middot; laravel.com/docs/tinker</span>
            </li>
        </ul>

        <h3>Data and templates</h3>
        <p>
            PCU error codes, success codes, PhilHealth identifiers and the Excel claim templates are
            the property of PhilHealth and the deploying clinic. They are configured per installation
            through <strong>Settings</strong> and are not distributed with this application.
        </p>

        <h3>Built by</h3>
        {{-- Filled in by the deploying clinic. Replace this block with your own
             details before going live; it is intentionally left as a placeholder
             rather than guessed at. --}}
        <p class="credits-todo">
            <strong>Placeholder &mdash; to be completed.</strong>
            Add the developing team, clinic or hospital name and any acknowledgements here.
        </p>
    </section>

</div>
@endsection