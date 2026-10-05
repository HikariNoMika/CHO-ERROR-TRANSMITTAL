{{--
    Birthdate field with paste support.

    The visible input is a plain text box so staff can paste a date from a
    referral sheet or a spreadsheet ("03/14/1990") instead of picking it from a
    calendar. The canonical Y-m-d value is kept in the hidden input that
    actually gets submitted, so the server contract is unchanged:
    PatientRecordRequest still validates 'required|date|before_or_equal:today'.

    Values are normalised on blur and again on submit, so pasting never leaves a
    half-typed string behind. Two-digit years are pivoted at 30: 30 and below
    become 20xx, above become 19xx.
--}}
@php
    $birthdateValue = old('birthdate', $birthdate ?? null);
@endphp

<div class="field">
    <label for="birthdate_input">Birthdate *</label>
    <input type="text"
           id="birthdate_input"
           data-birthdate-input
           value="{{ $birthdateValue ? \Illuminate\Support\Carbon::parse($birthdateValue)->format('m/d/Y') : '' }}"
           placeholder="mm/dd/yyyy"
           inputmode="numeric"
           autocomplete="off"
           maxlength="10"
           aria-describedby="birthdate_hint">
    <input type="hidden" name="birthdate" id="birthdate" value="{{ $birthdateValue }}">
    <p class="hint" id="birthdate_hint">Paste or type the birthdate, e.g. 03/14/1990</p>
    <p class="fielderror" data-birthdate-error hidden></p>
    @error('birthdate')<p class="fielderror">{{ $message }}</p>@enderror
</div>

@once
<script>
    (function () {
        var input = document.querySelector('[data-birthdate-input]');
        if (!input) return;

        var hidden = document.getElementById('birthdate');
        var error = document.querySelector('[data-birthdate-error]');
        var today = new Date();
        today.setHours(23, 59, 59, 999);

        function pad(n) { return (n < 10 ? '0' : '') + n; }

        function fail(message) {
            hidden.value = '';
            if (error) { error.textContent = message; error.hidden = false; }
        }

        function clearError() {
            if (error) { error.textContent = ''; error.hidden = true; }
        }

        /**
         * Parse mm/dd/yyyy (also tolerates 1-2 digit parts and 2-digit years),
         * then round-trip through Date so impossible dates such as 02/30 are
         * rejected instead of silently rolling into the next month.
         */
        function parseBirthdate(raw) {
            var value = (raw || '').trim();
            if (value === '') return { empty: true };

            var parts = value.split(/[/\-.]/);
            if (parts.length !== 3) return { error: 'Use mm/dd/yyyy, for example 03/14/1990.' };

            var month = parseInt(parts[0], 10);
            var day = parseInt(parts[1], 10);
            var year = parseInt(parts[2], 10);

            if (isNaN(month) || isNaN(day) || isNaN(year)) {
                return { error: 'Use mm/dd/yyyy, for example 03/14/1990.' };
            }
            if (parts[2].length <= 2) {
                year = year <= 30 ? 2000 + year : 1900 + year;
            }
            if (month < 1 || month > 12) return { error: 'Month must be between 01 and 12.' };
            if (day < 1 || day > 31) return { error: 'Day must be between 01 and 31.' };
            if (year < 1900) return { error: 'Please enter a four-digit year.' };

            var parsed = new Date(year, month - 1, day);
            // Date silently rolls over invalid dates (Feb 30 -> Mar 2), so
            // compare every component back against what was typed.
            if (parsed.getFullYear() !== year || parsed.getMonth() !== month - 1 || parsed.getDate() !== day) {
                return { error: 'That date does not exist. Please check the day and month.' };
            }
            if (parsed > today) return { error: 'Birthdate cannot be in the future.' };

            return { date: parsed };
        }

        function normalise(raw) {
            var result = parseBirthdate(raw);

            if (result.empty) {
                hidden.value = '';
                clearError();
                return true;
            }
            if (result.error) {
                fail(result.error);
                return false;
            }

            clearError();
            hidden.value = result.date.getFullYear() + '-'
                + pad(result.date.getMonth() + 1) + '-'
                + pad(result.date.getDate());

            // Reformat what the user sees so the field is always unambiguous.
            var canonical = pad(result.date.getMonth() + 1) + '/'
                + pad(result.date.getDate()) + '/'
                + result.date.getFullYear();
            if (input.value !== canonical) input.value = canonical;

            return true;
        }

        input.addEventListener('blur', function () { normalise(input.value); });
        input.addEventListener('input', function () {
            // Clear a stale error while the user is correcting the value.
            if (error && !error.hidden) clearError();
        });

        // Normalise before submit so a pasted value is never sent unvalidated.
        var form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function (event) {
                if (normalise(input.value)) return;
                event.preventDefault();
                input.focus();
            });
        }
    })();
</script>
@endonce