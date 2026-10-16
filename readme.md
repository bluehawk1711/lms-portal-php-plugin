# Student & Teacher Portal

A WordPress plugin that adds two front ends to an existing site:

- **Student portal** at `/portal/` — attendance, results, materials, certificates
- **Teacher portal** at `/teacher/` — take attendance, publish notes and documents, enter results

It is deliberately separate from the Certificate Manager plugin. Courses, students,
classes and enrolments all live here; that plugin is a **renderer**. The portal reads its
certificate records to show and verify certificates, and writes to them through one
guarded adapter (`cert-push.php`) at issuance — never to its schema, so replacing or
updating Certificate Manager cannot destroy the portal.

---

## Table of contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Roles and capabilities](#roles-and-capabilities)
4. [Routes](#routes)
5. [Admin screens](#admin-screens)
6. [How the request lifecycle works](#how-the-request-lifecycle-works)
7. [Database schema](#database-schema)
8. [Feature reference](#feature-reference)
9. [Settings](#settings)
10. [Security model](#security-model)
11. [Hooks reference](#hooks-reference)
12. [File map](#file-map)
13. [Schema versioning and migrations](#schema-versioning-and-migrations)
14. [Troubleshooting](#troubleshooting)

---

## Requirements

| | |
|---|---|
| WordPress | 6.0 or later (tested to 6.5) |
| PHP | 7.4 or later |
| Permalinks | Any structure **except** Plain |
| SMTP | Required for email delivery (see [Email](#email-and-the-email-log)) |

Certificate Manager is **optional**. Without it the portal keeps working; only the
Certificates tab and the Legacy data tools show a notice instead of data.

---

## Installation

1. Upload the `student-portal` folder to `wp-content/plugins/`, or install the zip
   via **Plugins → Add New**.
2. **Activate the plugin.** This creates the 12 `wp_sp_*` tables, both roles, and the
   `/portal/` and `/teacher/` anchor pages.
3. Go to **Settings → Reading** and pick a permalink structure other than Plain, then
   visit **Settings → Permalinks** and hit Save once so the rewrite rules register.
   Visiting `/portal/` or `/teacher/` also refreshes them.
4. Open **Student Portal → Settings** and confirm your pass mark and grade scale.
5. Install and configure an SMTP plugin. Without one, portal email does not arrive —
   every attempt is logged under **Student Portal → Email Log**.
6. Add teachers, import students, create classes, enrol students.

---

## Roles and capabilities

Two roles are created on activation:

| Role | Label | Capabilities |
|---|---|---|
| `sp_student` | Portal Student | `read` |
| `sp_teacher` | Portal Teacher | `read` |

Both carry **`read` only**, deliberately. A capability cannot express "this student, in
this class", so all real authorization is enforced in PHP against enrollment and
teacher-assignment tables.

> **Reading roles correctly.** `WP_User::$roles` is a plain **list** of role names —
> WordPress core builds it with `$this->roles[] = $key` in `WP_User::get_role_caps()`.
> So `isset( $user->roles['sp_teacher'] )` is *always* false, which silently makes every
> portal user look like a non-portal account ("That account is not a teacher portal
> account."). Use `sp_user_has_role()` — or `in_array( 'sp_teacher', $user->roles, true )`
> — never a string key lookup. `$user->caps` is the string-keyed map if you need that form.

Behaviour for portal users:

- `admin_init` → redirected away from `/wp-admin/` to their own dashboard
  (`sp_block_admin_for_portal_users`, priority 1). AJAX and cron are exempt.
- Admin bar hidden on the frontend.
- After `wp-login.php`, `login_redirect` sends them to the correct dashboard — but an
  explicit in-site destination is honoured so deep links still work.
- After logout, they land back on their own portal login.

**Administrators are never treated as portal users.** `sp_is_portal_user()` returns
false for anyone with `manage_options`, so admins keep full wp-admin access and can
still preview both portals.

---

## Routes

### Student — `/portal/`

| Route | Label | Template | Public |
|---|---|---|---|
| `/portal/` | Dashboard | `portal-dashboard.php` | Login form when logged out |
| `/portal/certificates/` | Certificates | `portal-certificates.php` | No |
| `/portal/attendance/` | Attendance | `portal-attendance.php` | No |
| `/portal/results/` | Results | `portal-results.php` | No |
| `/portal/materials/` | Materials | `portal-materials.php` | No |
| `/portal/profile/` | Profile | `portal-profile.php` | No |

The profile route is the only student screen that accepts a POST: name, phone and
date of birth are self-service (see *Student self-service profile*).

### Teacher — `/teacher/`

| Route | Label | Template |
|---|---|---|
| `/teacher/` | Dashboard | `teacher-dashboard.php` |
| `/teacher/classes/` | My classes | `teacher-classes.php` |
| `/teacher/classes/{id}/` | Single class | `teacher-class.php` |
| `/teacher/materials/` | Materials | `teacher-materials.php` |
| `/teacher/materials/new/` | New material | `teacher-material-edit.php` |
| `/teacher/materials/{id}/` | Edit material | `teacher-material-edit.php` |
| `/teacher/results/` | Results | `teacher-results.php` |

### Other

| URL | Purpose |
|---|---|
| `/?sp_material={id}` | Gated file stream (streams, never redirects; `inline=1` serves renderable types inline) |
| `?sp_sample=students` | Sample student CSV (admin only) |
| `?sp_sample=teachers` | Sample teacher CSV (admin only) |

### Rewrite rules

Registered on `init` at priority 5:

```php
^portal/?$                              → pagename=portal&sp_route=home
^portal/(certificates|attendance|results|materials|profile)/?$
                                        → pagename=portal&sp_route=$matches[1]

^teacher/?$                             → pagename=teacher&sp_teacher_route=home
^teacher/(classes|materials|results)/?$ → sp_teacher_route=$matches[1]
^teacher/materials/new/?$               → sp_teacher_route=material-edit
^teacher/materials/(\d+)/?$             → sp_teacher_route=material-edit&sp_material_id=$matches[1]
^teacher/classes/(\d+)/?$               → sp_teacher_route=class&sp_teacher_id=$matches[1]
```

Query vars: `sp_route`, `sp_teacher_route`, `sp_teacher_id`, `sp_material_id`.

### Rewrite rules are self-healing

WordPress stores the resolved rule set in the `rewrite_rules` option and only rebuilds it
on an explicit flush. Editing a route slug in code therefore leaves the stored copy stale
and every affected URL 404s until someone opens **Settings → Permalinks**.

To make that impossible, the plugin stamps `SP_REWRITE_VERSION` into `sp_rewrite_version`
and re-flushes on the first request after a change:

```php
add_action( 'init', 'sp_maybe_flush_rewrite_rules', 20 );
```

**Whenever you add, remove or rename a route, bump `SP_REWRITE_VERSION` in
`student-portal.php`.** It currently reads `'2'`. The check is one option read, so it is a
no-op on normal requests.

---

## Admin screens

Top-level **Student Portal** menu, gated on `manage_certificates` unless noted.

| Slug | Label | Capability |
|---|---|---|
| `sp-dashboard` | Dashboard | `manage_certificates` |
| `sp-add-student` | Add Student | `manage_certificates` |
| `sp-import` | Import (Students / Teachers tabs) | `manage_certificates` |
| `sp-student-accounts` | Student Accounts | `manage_certificates` |
| `sp-teachers` | Teachers | `manage_certificates` |
| `sp-courses` | Courses | `manage_certificates` |
| `sp-classes` | Classes | `manage_certificates` |
| `sp-enrollments` | Enrollments | `manage_certificates` |
| `sp-attendance` | Attendance | `manage_certificates` |
| `sp-results` | Results | `manage_certificates` |
| `sp-legacy-data` | Legacy data (3 tabs) | `manage_certificates` |
| `sp-settings` | Settings | `manage_options` |
| `sp-email-log` | Email Log | `manage_options` |

`sp-legacy-data` replaces the three screens phase 2 collapsed: **Enrollment Sync**,
**Grade Sync** and **Import from Certificates**.

---

## How the request lifecycle works

```
Browser
  │
  ├─ GET /portal/attendance/
  │     │
  │     ├─ init (pri 5)           sp_register_rewrite_rules()
  │     ├─ template_redirect (20) sp_maybe_render_portal()
  │     │      ├─ route known?       no → set_404() + status_header(404)
  │     │      ├─ wp_query->is_404?  yes → clear it, status 200
  │     │      ├─ logged out?        yes → redirect to portal home
  │     │      ├─ wrong role?        yes → redirect to own dashboard
  │     │      └─ sp_render_portal() → require template, own layout
  │     └─ exit
  │
  ├─ POST (login form on /portal/)
  │     └─ sp_handle_portal_login() → nonce → wp_signon() → record last_login_at
  │                                   → redirect, so refresh cannot re-post
  │
  └─ GET /?sp_material=123
        └─ template_redirect (5) sp_maybe_stream_material()
               ├─ sp_user_can_view_material()   (enrollment / assignment)
               ├─ sp_material_file_path()       (realpath inside uploads, no escape)
               └─ stream + exit
```

Key design points:

- **`template_redirect`, never a theme template.** The plugin renders its own minimal
  layout and never replaces content a theme might already be producing.
- **The anchor pages are never used as page templates.** They exist so WordPress
  resolves a permalink and so the slugs appear in the admin. If an anchor page is
  deleted, the handler clears `is_404` so legitimate routes still return 200.
- **Unknown slugs 404** rather than redirecting, so portal paths never leak their
  existence to logged-out visitors.
- **Every handler ends in a redirect or an exit**, so a browser refresh cannot repeat a
  write.

---

## Database schema

All tables use the `{prefix}sp_` prefix and are owned entirely by this plugin.
`VARCHAR` is used instead of `ENUM` because dbDelta handles ENUM changes badly, and
indexes are plain indexed BIGINTs with no InnoDB foreign keys.

| Table | Purpose | Notable columns |
|---|---|---|
| `sp_student_profiles` | One row per student | `user_id` UNIQUE, `email` UNIQUE, `cert_student_id` NULL, `enroll_id`, `dob` |
| `sp_teachers` | One row per teacher | `user_id` UNIQUE, `email` UNIQUE |
| `sp_courses` | A course the portal teaches | `name`, `code` (certificate id segment), `duration`, `status` |
| `sp_classes` | A class/course offering | `title`, `code`, `status`, `course_id` → `sp_courses`, `created_by`, **`batch_id` (legacy, non-unique)**, `timings_id` → `sp_timing_sets` |
| `sp_class_timings` | A class's weekly timetable | `class_id`, `day_of_week` (0 = Sunday), `start_time`, `end_time` |
| `sp_timing_sets` | A reusable weekly pattern | `name` (auto-generated label), `created_by` |
| `sp_timing_set_days` | The days of one timing set | `set_id`, `day_of_week`, `start_time`, `end_time` |
| `sp_teacher_classes` | Teacher ↔ class, many-to-many | `UNIQUE (teacher_id, class_id)`, `is_primary` |
| `sp_enrollments` | Student ↔ class | `UNIQUE (profile_id, class_id)`, `status`, **`source` (`manual`/`batch`)** |
| `sp_attendance_sessions` | One register per date | `class_id`, `session_date`, `status` (`open`/`submitted`), `submitted_at` |
| `sp_attendance_records` | Per-student mark | `UNIQUE (session_id, profile_id)` |
| `sp_attendance_audit` | Every change to a register | actor, action, reason |
| `sp_materials` | Notes and documents | `class_id`, `attachment_id`, `status` |
| `sp_results` | Theory + practical outcome | `enrollment_id`, `percentage`, `grade`, `result`, `finalised` |
| `sp_grade_sync_log` | Grade writes to Certificate Manager | old/new grade, who synced, when |
| `sp_email_log` | Every send attempted | `recipient`, `subject`, `status`, `error` |

Status values:

| Table | Values |
|---|---|
| `sp_enrollments.status` | `active`, `completed`, `dropped` |
| `sp_attendance_sessions.status` | `open`, `submitted` |
| `sp_materials.status` | `draft`, `published` |
| `sp_student_profiles.status` / `sp_teachers.status` | `active`, `disabled` |

---

## Feature reference

### Accounts and credentials

- Username **is** the email address. Email is mandatory for every student and teacher.
- `sp_create_account()` creates the WP user, assigns the role, and inserts the profile
  or teacher row.
- **Passwords are emailed as plaintext, exactly once.** WordPress only stores a hash,
  and `$user->user_pass` is the bcrypt digest (`$wp$2y$10$…`) — emailing *that* produces
  a credential nobody can type, and the login form will always reject it. Callers pass
  the real password to `sp_email_credentials()`, which is why the welcome mail is built
  at account-creation time rather than read back from the user record.
- Because the password cannot be recovered later, **Resend credentials** issues a brand
  new one rather than trying to re-derive it.
- **Password field:** optional for students (blank → a strong random password is
  generated and emailed) and **required for teachers**, on both the single-add forms
  and CSV import. Teacher rows without one are reported as errors in the dry run rather
  than silently getting a generated password.
- **Date of birth** is an optional student field, accepted from the Add Student form,
  the CSV importer (`dob`, `date_of_birth`, `birth_date`, `birthdate`) and shown on the
  student's profile page.
- **Disable** (Student Accounts → Disable) sets status inactive *and* resets the
  password, so the account cannot sign in.
- On every successful portal login, `last_login_at` is stamped on the profile or
  teacher row.
- If a user signs in successfully but holds no portal role, they are logged straight
  back out and shown a `not_portal` message — a valid account is never silently
  treated as a portal user.
- **Specific login errors only surface after a successful password check.** A disabled
  account returns `sp_account_disabled`; a valid login with the wrong portal returns
  `not_portal`. Unknown email and wrong password deliberately stay combined
  (`credentials_error`) so the form cannot be used to discover which addresses exist.
- **Login passwords are never re-shown or cleared after a failed attempt**, so a typo
  can be corrected without retyping. Both login forms carry a show/hide toggle.

### Student self-service profile

`/portal/profile/` is editable by the student: **name, phone and date of birth** only.
The form posts back to `/portal/profile/` with a `sp_portal_action_save_profile` nonce
and is handled by `sp_portal_action_save_profile()`, which sanitises the phone to
digits, validates the date with an explicit `Y-m-d` round trip (an impossible date such
as `2024-02-31` is rejected rather than rolled over) and writes to
`sp_student_profiles`. An empty name keeps the previous value so the roster can never be
blanked.

Email, username, enrolment ID, class memberships and status stay admin-managed — those
key the certificate record and the roster. Changes are written to `activity_log`.

### Courses

Courses are **portal-owned** (table `sp_courses`, schema 2.0). They used to be read
straight from the Certificate Manager, which quietly made that plugin the source of truth
for curriculum names.

- Managed on **Student Portal → Courses**: name, code, duration, description and status.
- The **code** is what the Certificate Manager stamps into a certificate id
  (`SWJOD/{code}/{year}/{random}`), so duplicates are rejected and only letters, digits
  and dashes survive. Blank means the certificate falls back to `GEN`.
- A class picks one course; that is how the code reaches an issued certificate.
- On the first install after 2.0 the table is seeded once from `wp_cert_courses`, copying
  **ids verbatim** so every existing `sp_classes.course_id` keeps pointing at the same
  course with no value migration.
- A course cannot be deleted while a class still teaches it.

### Classes

- A class MAY carry a **batch** id, but only classes linked before phase 2 ever do: the
  class form no longer offers one, and an ordinary class save never clears a stored link.
  Batch enrolment lives on the [Legacy data](#legacy-data--enrol-from-batches) screen.
- Teachers can create classes; they can only *see* classes assigned to them.
- Creating a class auto-assigns the creator as primary teacher.
- A `code` lets students be matched to a class during CSV import
  (`sp_find_class_by_code()`).

### Weekly timings

- The class form carries seven day rows (start and end time each). A blank pair means the
  class does not meet that day; a half-filled pair is rejected before anything is saved.
- What gets saved is two things at once: the class's own timetable rows in
  `sp_class_timings`, and the **timing set** that pattern belongs to. Classes that meet
  identically share one `sp_classes.timings_id`, so "which classes meet like this" is an
  indexed lookup instead of a row-by-row comparison.
- A set is created only when a class is saved — there is no screen for them yet, and
  nothing deletes them. When you edit a class the form shows its pattern and how many
  other classes share it.
- The timetable is inert so far: phase 4 reads it to decide which days should have a
  register.

### Enrollment

- **Admin-only authority.** Teachers read the roster; they never add or drop students.
- `UNIQUE (profile_id, class_id)` makes double-enrollment impossible.
- Status moves between `active`, `completed` and `dropped`; history is kept.
- Every enrolment records its `source`: `manual` (entered by hand) or `batch` (created by
  the Legacy data screen). This is what lets that screen modify its own rows and never
  touch yours.

### Legacy data — enrol from batches

**Student Portal → Legacy data → Enrol from batches** copies batch membership into class
rosters so the same students are not enrolled twice. It is a one-way, idempotent,
administrator-triggered copy — **the portal's `sp_enrollments` stays authoritative** and
nothing is ever written to the Certificate Manager.

It only matters for classes that still carry a historical batch link; classes created now
never have one.

For a class linked to a batch, one sync run:

| Action | When |
|---|---|
| **Create** | A portal profile is linked to a certificate record in that batch and has no enrolment yet |
| **Reactivate** | Such an enrolment existed but had been retired |
| **Drop** | A `batch`-sourced enrolment the batch no longer backs (e.g. the student moved batch) — history is kept |
| **Manual** | An enrolment you entered by hand — reported and **never modified** |
| **Unlinked** | A student in the batch with no portal account yet — reported, not silently skipped |

Verified lifecycle:

| Step | Result |
|---|---|
| First sync | `created=1` |
| Second sync | all zero — idempotent |
| Student moved to another batch | `dropped=1`, status `dropped`, `source=batch` |
| Moved back | `reactivated=1`, status `active` |
| Existing manual enrolment | counted as `manual`, untouched |

Certificate records with **no** batch can never sync, so the screen lists them for you to
fix in the Certificate Manager.

If the Certificate Manager plugin is inactive the screen explains the situation and
everything else keeps working.

### Attendance

A per-session, per-date register.

**States:** `present`, `absent`, `late`, `excused`.

**Flow:**

1. Teacher opens a register for a class and date (`sp_create_session()` — reuses the
   existing session if one exists for that date).
2. Marks every student and bulk-saves.
3. **Submit** locks it: `status = 'submitted'`, `submitted_at` stamped.
4. Reopen is allowed for `attendance_edit_days` (default 7) days after submission.
5. An administrator may always reopen, and must give a reason.

**The edit window is enforced server-side.** `sp_attendance_session_is_editable()` is
called from every save handler, not just used to hide UI, so a stale open tab cannot
write past the window.

**Percentage** — `sp_get_attendance_stats()` in `repository.php`, defined in exactly
one place:

```php
percentage = round( ( present + late ) / ( total - excused ) * 100, 1 )
where total = present + absent + late + excused
```

`late` counts as attended, and `excused` is removed from the denominator entirely, so
an authorised absence neither helps nor hurts the score. When every session is excused
the denominator is zero and the result is reported as `0.0` rather than dividing by zero.

Verified behaviour:

| Marked | Percentage |
|---|---|
| 8 present, 2 excused | 100% |
| 8 present, 2 late | 100% |
| 5 present, 5 absent | 50% |
| 6 present, 2 absent, 2 excused | 75% |
| 0 present, 5 excused | 0% |

**Concurrency:** `UNIQUE (session_id, profile_id)` with `ON DUPLICATE KEY UPDATE`, so
two simultaneous saves leave exactly one record per student.

**Audit:** every reopen, correction and submission writes to `sp_attendance_audit`.

### Results and grading

Four inputs per enrollment: `theory_max`, `theory_obtained`, `practical_max`,
`practical_obtained`.

`sp_calculate_result()` validates and computes:

1. Maximums cannot be negative; at least one maximum must be greater than zero.
2. Obtained cannot be negative.
3. Obtained cannot exceed its own maximum (`sp_theory_over`, `sp_practical_over`).
4. `percentage = round( (theory_obtained + practical_obtained) / (theory_max + practical_max) * 100, 2 )`, clamped to 0–100.
5. `result` is `pass` when `percentage >= pass_mark` (default 35).
6. `grade` comes from the configured descending scale.

Rounding is applied before the pass comparison so a stored 34.99 rounding up to 35 is
not failed by a hair.

**Grade scale** — descending thresholds, stored in `sp_settings['grade_scale']`:

| Minimum % | Grade |
|---|---|
| 90 | A+ |
| 75 | A |
| 60 | B |
| 45 | C |

Below 45 yields an empty grade and a fail.

**Finalisation** is effectively one-way from the teacher screen: `sp_save_result()` only
sets `finalised = 1`, never back to 0. An administrator can unfinalise. The student
email fires **only on the 0 → 1 transition**, so editing a finalised result does not
re-send mail.

### Materials and inline document previews

Notes (rich text) and documents, per class, `draft` or `published`. Students see only
published materials for classes they are enrolled in.

**Storage** — files live in `wp-content/uploads/sp-private/YYYY/MM/` under a **32 random
hex character** name, so the on-disk path cannot be guessed or enumerated. The folder
carries its own `Require all denied` `.htaccess`, so the only way to read a file is the
gated route below. The original filename is preserved separately in `sp_materials` for
display, sanitised by `sp_safe_content_filename()`.

**Serving path** — `/?sp_material={id}` runs `sp_maybe_stream_material()`:

1. `sp_user_can_view_material()` checks enrollment (student) or assignment (teacher).
2. `sp_material_file_path()` resolves the stored `file_path` (or a legacy
   `attachment_id`) and requires `realpath()` to land **inside** `WP_CONTENT_DIR/uploads`.
   Anything resolving outside returns `sp_path_escape` and 404s.
3. The file is streamed with the right headers and the script exits. With
   `inline=1`, types a browser can render (PDF, images, plain text) get
   `Content-Disposition: inline` plus `Content-Security-Policy: frame-ancestors 'self'`;
   everything else still downloads as `attachment`.

**No downloads in the student portal.** `/portal/materials/` embeds every file
instead of linking it:

| Kind | How it renders |
|---|---|
| `note` | Rich text, inline (`wp_kses_post` + `wpautop`) |
| PDF, txt, csv | `<iframe>` on the gated route with `inline=1` |
| png / jpg / jpeg / gif / webp | `<img>` on the same gated URL |
| docx | Converted to HTML in the browser by `material-embed.js` through the Mammoth .docx converter plugin's bundle (printed on demand by `sp_docx_preview_host()`; without that plugin the card degrades to “preview not available”) |
| doc, ppt, pptx, xls, xlsx | “Preview not available” card — no browser renders these, and the Google/Microsoft cloud viewers cannot reach login-gated files |

The dashboard's *Recent materials* list is just a pointer: both kinds link to
`/portal/materials/#material-{id}` so the document opens in its embedded form. The
`sp_material_url()` route is still used internally (teacher edit screen preview) and
for anything that must be fetched as a file, but no student-facing template prints a
download button any more.

**Upload allowlist** — extension **and** MIME must both pass:

| Extension | Accepted MIME types |
|---|---|
| `pdf` | `application/pdf` |
| `doc` | `application/msword`, `application/octet-stream` |
| `docx` | `…wordprocessingml.document`, `application/zip` |
| `ppt` | `application/vnd.ms-powerpoint`, `application/octet-stream` |
| `pptx` | `…presentationml.presentation`, `application/zip` |
| `xls` | `application/vnd.ms-excel`, `application/octet-stream` |
| `xlsx` | `…spreadsheetml.sheet`, `application/zip` |
| `txt` | `text/plain` |
| `csv` | `text/plain`, `text/csv`, `application/csv` |
| `png` `jpg` `jpeg` `webp` | matching image types |

**Always rejected, whatever the MIME says:**

```
php php3 php4 php5 php7 php8 phtml phps pht html htm shtml svg js json htaccess user.ini
```

Size cap from `max_upload_mb` (default 20).

> **Accepted tradeoff.** Files live in the shared uploads tree, so a leaked direct URL is
> fetchable without login. Random 32-hex names remove guessability and the gated route
> enforces enrollment. `wp-content/uploads/.htaccess` blocks PHP execution there as
> defense in depth. If hard protection is later required, move the folder outside the
> webroot and stream from there.

### Certificates

- Read from the Certificate Manager's `cert_students` table.
- The portal lists only rows with `status = 'generated'` and links to the public
  `/verify-certificate/?cert_id=…` page. Certificates are **public** — the portal makes
  no privacy claim.
- `cert_student_id` is only a cache. Links are resolved by `enroll_id`, the vendor UNIQUE
  key, because Certificate Manager's "delete all" uses `TRUNCATE`, which resets ids.
  Stale ids are detected and repaired by `sp_link_cert_student()`.
- If Certificate Manager is inactive, `sp_certificate_manager_active()` returns false and
  affected screens render a notice instead of fataling.

### Certificate writes

**Every** write to a Certificate Manager table goes through `includes/cert-push.php`:

1. `sp_cert_mirror_course()` ensures `wp_cert_courses` has a row carrying the portal
   course's code — ids are kept aligned with `sp_courses` whenever that id is free —
   so the certificate id gets the right course code.
2. `sp_push_certificate_record()` upserts `wp_cert_students` **keyed on `enroll_id`** with
   the student's name, the **class title**, the course id, the year, the dates and the
   grade, then points `sp_student_profiles.cert_student_id` at whichever row it lands on.
3. A grade that actually changed is recorded in `sp_grade_sync_log` with old value, new
   value and who did it — the same audit trail Grade Sync used to own.
4. The vendor's `cert_mgr_generate_certificate()` renders the PDF from that row.

The write is idempotent: issuing twice rewrites the same row rather than creating a
second one, and the portal never alters the vendor schema. If Certificate Manager is
missing, issuance fails with a clear message while everything else keeps working.

**Student Portal → Legacy data → Write history** shows step 3.

Teachers can never trigger a write; issuance is an administrator action.

### Legacy data — link certificate records

**Student Portal → Legacy data → Link certificate records** matches Certificate Manager
records to portal accounts. Dry-run first, exactly like the CSV importer. This is for
students who predate the portal — a student created from the portal is linked
automatically the first time a certificate is issued for them.

Matching is on `enroll_id`, the vendor's UNIQUE key, because that survives the `TRUNCATE`
that resets numeric ids. Rows are classified as:

| Action | Meaning |
|---|---|
| **Link** | A portal account exists for this enroll id — attach the certificate record |
| **Linked** | Already attached; nothing to do |
| **Needs email** | No portal account exists — see below |

**The vendor table stores no email address**, so a record with no existing account cannot
become a working login by itself. Those rows are reported rather than silently created.
The intended flow is: create the accounts from your student list (CSV import or Add
Student), then return here and link them.

> **Collation note.** Certificate Manager tables use `utf8mb4_unicode_520_ci` while this
> plugin uses `utf8mb4_unicode_ci`. Comparing `enroll_id` across the two raises *"Illegal
> mix of collations"*, so every cross-table string comparison pins an explicit
> `COLLATE utf8mb4_unicode_ci`. This avoids altering the vendor schema.

### Issuing a certificate

A **passing grade is all that is needed** — the result does not also have to be published
first. Issuing pushes the record (see [Certificate writes](#certificate-writes)) and asks
the Certificate Manager to produce the PDF, QR code and verification link. A student with
no certificate record yet gets one created, so a portal-only student can be certificated
without any import step.

On **Student Portal → Results**:

| State | What you see |
|---|---|
| Grade entered and passing | **Issue certificate** button |
| Already issued | **View** (opens the public verify page) and **Reissue** |
| Failing / not graded | `—`, with the reason on hover |
| Student has no enrolment ID | `—` — the reason explains what to set |

A **Issue all *N* pending certificate(s)** button appears above the table whenever a class
has students who are eligible. Bulk issuance skips anyone already issued, failing, or not
yet graded, and reports problems individually.

`sp_certificate_state_for_enrollment()` is the single source of that decision, so the
screen and the handlers can never disagree.

### Automatic generation

Opt-in, **off by default**. When `auto_certificate` is on, finalising a passing result
immediately:

1. pushes the certificate record (name, class title, year, dates and grade) and logs any
   grade change to `sp_grade_sync_log`,
2. calls `cert_mgr_generate_certificate()` to build the PDF, QR code and verify link.

Guards, all no-ops that never fatal:

| Condition | Behaviour |
|---|---|
| Result is a fail | Never certificated |
| Student has no enrolment ID | Skipped (reported if issued by hand) |
| Grade below `auto_certificate_min_grade` | Skipped |
| Certificate Manager inactive | Skipped |

`auto_certificate_min_grade` is optional — empty means any passing result. When set, both
grades are resolved against the configured scale, so "B or better" means B (60%) and above.

A **Generate certificate** button also appears per-row on the admin Results screen for any
finalised passing result, bypassing the auto setting. It still refuses a failing student.

### Email and the email log

Every send goes through `sp_email_portal()` and is written to `sp_email_log` with status
`sent` or the failure message.

| Trigger | Setting | Default |
|---|---|---|
| Result finalised (0 → 1 only) | `notify_on_result` | on |
| Material published | `notify_on_material` | on |
| Teacher assigned to a class | `notify_on_assignment` | off |
| Student enrolled | `notify_on_enrollment` | off |

**SMTP is an operational prerequisite.** Without it, delivery fails; the failure is
recorded in the log rather than surfacing as a silent no-op. Use **Email Log** and the
built-in "Send test email" on the Settings screen to confirm delivery works.

### CSV import

Students and teachers are imported from **one screen** — *Student Portal → Import* —
with a **Students** tab and a **Teachers** tab. Both are **dry-run first**: parsing
produces a plan of creates, skips and errors that you must confirm before anything is
written. Re-running the same file skips every row as a duplicate. Every result redirect
carries `tab=`, so the screen you came from is the screen you land back on.

| | Students | Teachers |
|---|---|---|
| Tab | `?page=sp-import&tab=students` (default) | `?page=sp-import&tab=teachers` |
| Accepted extensions | `.csv`, `.txt` | `.csv`, `.txt` |
| Required column | email | email |
| Optional | name, enroll_id, class code, admission date | name, phone |
| Sample download | `?sp_sample=students` | `?sp_sample=teachers` |

---

## Settings

Single option array `sp_settings`, defaults merged over stored values.

| Key | Default | Meaning |
|---|---|---|
| `pass_mark` | `35` | Percentage needed to pass |
| `grade_scale` | A+ 90 / A 75 / B 60 / C 45 | Descending thresholds |
| `attendance_edit_days` | `7` | Reopen window after submit. **0 locks immediately** |
| `off_days` | `[0]` | Weekdays off — Sunday by default. No class, no register |
| `max_upload_mb` | `20` | Material upload cap |
| `notify_on_result` | `1` | Email on finalisation |
| `notify_on_material` | `1` | Email on publish |
| `notify_on_assignment` | `0` | Email on assignment |
| `notify_on_enrollment` | `0` | Email on enrolment |
| `auto_certificate` | `0` | Generate certificate on finalising a passing result |
| `auto_certificate_min_grade` | `''` | Minimum grade for automatic certificates; empty = any pass |

Other options: `sp_db_version` (schema version), `sp_delete_data_on_uninstall`
(opt-in, checked by `uninstall.php`).

## Security model

| Concern | How it is handled |
|---|---|
| Every POST | Nonce **and** role check **and** object-level check (enrollment / assignment) |
| Teacher on an unassigned class | 403 from `sp_require_assigned_teacher()` on every class-scoped route; dashboards list only assigned classes |
| Attendance edit past the window | Re-checked server-side in the save handler, independent of what the UI showed |
| Dangerous upload | Extension **and** MIME allowlist; the forbidden list is checked first |
| Path traversal on download | `realpath()` must resolve inside `WP_CONTENT_DIR/uploads`, else 404 |
| Deleted / non-attachment file | `post_type` verified, path existence verified, else 404 |
| Unauthenticated hit on `/portal/*` or `/teacher/*` | Redirect to that role's login; unknown slugs 404 — no redirect loops |
| Capability alone | Never trusted; both portal roles hold only `read` |
| Error messages | Generic where detail would leak whether an account exists |

### Uninstall

`uninstall.php` deletes **nothing** unless `sp_delete_data_on_uninstall` is set. Even
then it drops tables, options and roles, and deletes only accounts that used a portal
role. The anchor pages are deliberately left in place because other pages may link to
them. Deactivation never removes data — it only flushes rewrite rules.

---

## Hooks reference

| Hook | Callback | Priority |
|---|---|---|
| `activate_student-portal/student-portal.php` | `sp_activate` | — |
| `deactivate_student-portal/student-portal.php` | `sp_deactivate` | — |
| `plugins_loaded` | `sp_maybe_upgrade` | 10 |
| `init` | `sp_register_rewrite_rules` | 5 |
| `init` | `sp_maybe_flush_rewrite_rules` | 20 |
| `template_redirect` | `sp_maybe_stream_material` | 5 |
| `template_redirect` | `sp_maybe_render_portal` | 20 |
| `template_redirect` | `sp_maybe_render_teacher_portal` | 20 |
| `admin_menu` | `sp_register_admin_menu` | 10 |
| `admin_init` | `sp_block_admin_for_portal_users` | 1 |
| `admin_init` | `sp_handle_admin_action` | 5 |
| `admin_init` | `sp_admin_sample_csv` | 10 |
| `admin_init` | `sp_register_settings` | 10 |
| `admin_enqueue_scripts` | `sp_admin_enqueue_assets` | — |
| `wp_enqueue_scripts` | `sp_portal_enqueue_assets` | — |
| `wp_enqueue_scripts` | `sp_teacher_enqueue_assets` | 21 |
| `query_vars` | `sp_portal_query_vars` | — |
| `show_admin_bar` | `sp_hide_admin_bar_for_portal_users` | — |
| `login_redirect` | `sp_login_redirect` | 10 |
| `logout_redirect` | `sp_logout_redirect` | 10 |

### Key functions

| Function | File | Purpose |
|---|---|---|
| `sp_install()` | `db.php` | Create/migrate schema |
| `sp_migrate_offering_to_class()` | `db.php` | 1.1 offering → class rename |
| `sp_migrate_courses_from_vendor()` | `db.php` | 2.0 one-time course seed |
| `sp_get_attendance_stats()` | `repository.php` | **Single source** of attendance % |
| `sp_attendance_session_is_editable()` | `settings.php` | Reopen-window check |
| `sp_calculate_result()` | `grading.php` | Marks → percentage/grade/pass |
| `sp_grade_for_percentage()` | `grading.php` | Percentage → grade |
| `sp_require_assigned_teacher()` | `teacher-routes.php` | Assignment gate |
| `sp_user_has_role()` | `roles.php` | **Correct** role check (`roles` is a list, not a map) |
| `sp_user_can_view_material()` | `materials.php` | Download authorization |
| `sp_material_file_path()` | `materials.php` | Traversal-safe resolution |
| `sp_certificate_manager_active()` | `db.php` | Vendor presence probe (cached) |
| `sp_push_certificate_record()` | `cert-push.php` | **The only vendor write** (plus its course mirror) |
| `sp_save_course()` | `courses.php` | Portal-owned course CRUD |
| `sp_save_class_timings()` | `timings.php` | Timetable + timing set in one save |
| `sp_get_class_timings()` | `timings.php` | Reads a class's week back as `HH:MM` rows |

---

## File map

```
student-portal/
├── student-portal.php          Bootstrap: constants, requires, activation hooks
├── uninstall.php               Opt-in teardown only
├── readme.txt                  WordPress.org format
├── readme.md                   This file
├── assets/
│   ├── css/  admin.css  portal.css  teacher.css
│   └── js/   portal.js  teacher.js
├── includes/
│   ├── db.php                  Schema, versioning, Certificate Manager guards
│   ├── roles.php               Roles, caps, admin blocking, login redirects
│   ├── repository.php          All queries (students, classes, attendance, materials)
│   ├── classes.php             Class CRUD, teacher assignment, enrollment
│   ├── courses.php             Portal-owned course CRUD
│   ├── timings.php             Weekly timetable + reusable timing sets
│   ├── attendance.php          Sessions, bulk save, submit, reopen, audit
│   ├── grading.php             Percentage, grade, pass/fail
│   ├── results.php             Result persistence and finalisation
│   ├── materials.php           Upload validation, storage, gated streaming
│   ├── grade-sync.php          Reader for the certificate write history
│   ├── certificates.php        Certificate state, auto + manual issuance
│   ├── cert-push.php           The single adapter that writes to Certificate Manager
│   ├── cert-import.php         Link certificate records to portal accounts
│   ├── batches.php             Batch listing + enrollment sync from batches
│   ├── accounts.php            Account creation, credentials, status
│   ├── mailer.php              Email composition, sending, logging
│   ├── import.php              CSV parsing, dry-run planning, execution
│   ├── settings.php            Option defaults, sanitisation, edit-window rules
│   ├── portal-routes.php       /portal/ rewrite rules, dispatch, login
│   ├── portal-render.php       Student layout and asset loading
│   ├── teacher-routes.php      /teacher/ rewrite rules, dispatch, login, authz
│   ├── teacher-render.php      Teacher layout, POST dispatch, asset loading
│   ├── admin-menu.php          Menu registration, enqueue, sample CSV
│   ├── admin-actions.php       Every admin POST handler (nonce-checked)
│   └── admin/                  One file per screen (legacy-data.php hosts two tabs)
└── templates/
    ├── portal-*.php            Student screens
    └── teacher-*.php           Teacher screens
```

## Schema versioning and migrations

The installed version is stored in the `sp_db_version` option and compared against
`SP_DB_VERSION` in `db.php`.

```
SP_DB_VERSION = '2.1'
```

On activation, and on any later request where the stored version is behind,
`sp_maybe_upgrade()` calls `sp_install()`:

1. `sp_migrate_offering_to_class()` — the 1.1 migration (see below).
2. `dbDelta()` over `sp_schema_statements()` — which also adds every column and
   table introduced since the tables were first created.
3. `sp_migrate_courses_from_vendor()` — the 2.0 seed (see below), which needs the
   table created in step 2.
4. `update_option( 'sp_db_version', SP_DB_VERSION )`.

### Migration 1.0 → 1.1 (offering → class)

"Offering" was renamed to "class" throughout the plugin. Because `dbDelta()` cannot
rename tables or columns, `sp_migrate_offering_to_class()` does it explicitly:

```php
wp_sp_offerings          → wp_sp_classes
wp_sp_teacher_offerings  → wp_sp_teacher_classes
offering_id              → class_id   (classes, teacher_classes, enrollments,
                                      attendance_sessions, materials)
```

It is **idempotent**: each step is skipped when the new name already exists and the old
one does not, guarded by `sp_table_exists()` and `sp_column_exists()`. No data is
dropped. Only the migration function itself still contains the string "offering".

**When adding a migration:** bump `SP_DB_VERSION`, add a function that checks before it
acts, call it from `sp_install()` around `dbDelta()` (before it, unless the step needs a
table or column that `dbDelta()` is about to create), and never drop a column.

### Migration 1.1 → 1.2 (student date of birth)

Adds a nullable `dob date` column to `sp_student_profiles`. `dbDelta()` adds the column
itself, so no bespoke step is needed — existing rows simply read `NULL` and the profile
page shows "Not recorded".

### Migration 1.2 → 1.3 (class ↔ batch linking)

Two additive columns, both added by `dbDelta()` with no bespoke step:

- `sp_classes.batch_id` — nullable, indexed, **not unique**
- `sp_enrollments.source` — `varchar(20)` defaulting to `'manual'`

Existing rows read `NULL` / `'manual'`, which is the correct starting state: no class is
linked yet and every enrolment is treated as hand-entered, so the first sync can never
modify an enrolment you created by hand. Nothing needed backfilling because
`sp_enrollments` was empty.

### Migration 1.4 → 2.0 (portal-owned courses)

`dbDelta()` creates the new `sp_courses` table. Then `sp_migrate_courses_from_vendor()`
runs **once**, and only while that table is empty:

- every `wp_cert_courses` row is copied across with its **id intact**, so
  `sp_classes.course_id` values keep meaning the same course with no value migration;
- the portal becomes the source of truth from then on: the vendor table is never read for
  the course picker again, and is only written by the issuance adapter when a course's
  code has to reach a certificate;
- a site with no Certificate Manager (or an empty course table) simply starts empty.

Nothing is dropped: `sp_classes.batch_id` stays in the schema and keeps whatever links it
already has — only the class **form** stopped offering it.

### Migration 2.0 → 2.1 (weekly timings)

Entirely additive, so `dbDelta()` does the whole job:

- three new tables — `sp_class_timings`, `sp_timing_sets`, `sp_timing_set_days`;
- one new nullable indexed column — `sp_classes.timings_id`.

Existing classes read `NULL` and have no timetable, which is the correct starting state:
nothing schedules a register from a class until an admin saves a timetable for it. No
backfill, no data rewrite, no bespoke migration function.

---

## Troubleshooting

**Plugin activates but the page downloads a CSV instead of rendering**
A handler was registered on `admin_init` without guarding its trigger. Any `admin_init`
callback that calls `header()` + `exit` must first confirm the request is actually
asking for it. See `sp_admin_sample_csv()`.

**`/portal/` returns 404**
Permalinks are set to Plain, or rewrite rules were never flushed. Set a non-Plain
structure, then visit **Settings → Permalinks** and Save, or simply load `/portal/`.

**A `/portal/*` or `/teacher/*` URL 404s**
Two causes. Either the permalink structure is set to Plain, or the stored rewrite rules
are stale after a route change. Visiting the URL once, or saving
**Settings → Permalinks**, rebuilds them; the plugin also self-flushes when
`SP_REWRITE_VERSION` is bumped. Check the live rule with:
`SELECT option_value FROM wp_options WHERE option_name = 'rewrite_rules';` — it must say
`classes`, not `offerings`.

**`/wp-admin/` is not redirecting a student**
Confirm the user actually holds `sp_student` and does **not** have `manage_options` —
admins are intentionally exempt.

**Teacher gets 403 on a class**
They are not assigned to it. Assign via **Student Portal → Classes**, or ask an admin.

**Fatal error: "Call to undefined function …"**
Two causes. Either a typo in a WordPress function name (`get_current_user_id()`, not
`current_user_id()`), or a function that got **nested inside another function** by a bad
edit. Neither is caught by `php -l`: a nested declaration is valid PHP, it just only
exists once the enclosing function runs. After editing, check that every `sp_*` call has
a matching top-level definition, and that nothing is declared at brace depth > 0.

**Attendance won't save**
Two causes. Either the register was submitted more than `attendance_edit_days` ago — an
administrator must reopen it — or the chosen date is an **off day** (Sunday by default),
which blocks opening and marking entirely.

**A submitted register can still be edited**
It should not be. Administrators have no automatic bypass: they must use the explicit
**Reopen register** action, which is recorded in `sp_attendance_audit`. If a submitted
register is still editable, confirm `attendance_edit_days` actually saved (see
**Settings changes do not stick**).

**Settings changes do not stick**
The Settings form posts back to `admin.php`, not `wp-admin/options.php`, so WordPress's
Settings API never processed it. The save handler now sanitises and writes the option
itself. If you see a stale value, another plugin may be writing `sp_settings`.

**Attendance can be taken on a Sunday**
Check **Settings → Off days**. The default is Sunday only. Off days block
`sp_create_session()` server-side as well as hiding the UI, so the server rejects it even
if the form is stale.

**Login says "That account is not a teacher/student portal account"**
The account signed in successfully but holds no portal role. Check the user actually has
the `sp_student` / `sp_teacher` role on the account screen — a user created before the
roles existed, or with no role at all, will always hit this. Note that testing roles must
use `sp_user_has_role()`; `isset( $user->roles['sp_teacher'] )` is always false because
`$user->roles` is a list.

**Login says "That account is disabled"**
The password was correct but the profile or teacher row has `status` other than
`active`. The message is only produced *after* the password verifies, so it is never
used to probe which addresses exist. Re-enable the account from **Student Accounts** or
**Teachers**.

**Emails not arriving**
Install and configure an SMTP plugin, then use the test button on **Settings** and check
**Email Log**. Every attempt is recorded with its failure reason.

**The emailed password looks like `$wp$2y$10$…` and the login form rejects it**
That was a bug, fixed in this release: the email was sending `$user->user_pass`, which is
the stored bcrypt hash rather than the password. No hash can ever sign in. Re-issue
credentials from **Student Accounts → Resend credentials** so affected accounts receive a
real password.

**Teacher created without a password**
Expected — teacher passwords are required. Supply one on the form or in the CSV
`password` column; the dry run reports the offending row.

**Certificates tab is empty**
Either Certificate Manager is inactive, or the student has no generated certificate. The
tab shows a notice when the plugin is missing.

**A material shows “Preview not available”**
The file type cannot be rendered in a browser (doc/ppt/xls), or the file was
deleted from disk. `sp_material_file_path()` refuses to serve a missing file
rather than guessing, and the card explains which case the student is in.

**A grade will not save**
Obtained marks cannot exceed their maximum, and at least one maximum must be greater than
zero. Check the error text returned by `sp_calculate_result()`.

**Attendance percentage looks wrong**
The formula lives only in `sp_get_attendance_stats()`. See
[Attendance](#attendance) for the exact current behaviour.

**"Illegal mix of collations" from the certificate screens**
A cross-table string comparison lost its explicit `COLLATE`. Certificate Manager uses
`utf8mb4_unicode_520_ci` and this plugin `utf8mb4_unicode_ci`. Any comparison of
`enroll_id` between the two needs `COLLATE utf8mb4_unicode_ci` on both sides.

**A student shows "Needs email" on Legacy data → Link certificate records**
Expected. The Certificate Manager stores no email address, so the portal cannot create a
login from that record alone. Create the account first (CSV import or Add Student), then
link it there. A student the portal created does not need this at all: issuing their first
certificate links the record automatically.

**No certificate is generated automatically**
Check, in order: `auto_certificate` is on in Settings; the result is a **pass**; the
student has an enrolment ID; and the grade meets
`auto_certificate_min_grade`. Certificate Manager must be active. Failures are silent by
design — nothing is emailed or logged to the portal when a certificate is skipped.

---

## License

GPLv2 or later.
