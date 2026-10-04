@extends('layouts.app')

@section('page-title')
    <h2 class="page-title">Documentation</h2>
@endsection

@section('content')
<div class="docs">

    <nav class="docs-nav" aria-label="Sections">
        <a href="#documentation">Documentation</a>
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