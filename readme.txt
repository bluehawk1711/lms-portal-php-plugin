=== Student & Teacher Portal ===
Contributors: swavalambankendra
Tags: students, teachers, portal, attendance, certificates, results
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A student and teacher portal: accounts, classes, attendance, results, course notes and documents — reading your certificate records without touching their tables.

== Description ==

The portal adds two front ends to your existing WordPress site:

**Student portal** at `/portal/`
* Sign in with the email address your institute registered
* See your courses, attendance percentage and per-session history
* Read published notes and view documents inline (PDF, images and Word files
  are embedded right on the page — there is nothing to download)
* View published results with theory/practical breakdown, percentage and grade
* View, verify and download your certificates

**Teacher portal** at `/teacher/`
* Create classes and see the classes assigned to you
* Take attendance on a phone with large tap targets, then submit and lock the register
* Write rich-text notes and upload documents, as a draft or published
* Enter theory and practical marks and publish results to students

**Administration** under the Student Portal menu
* Import students and teachers from CSV, always with a dry-run preview first
* Manage courses, classes, assign teachers, enrol students
* Correct attendance, publish results, review what is outstanding
* Issue certificates from the Results screen; every write to the Certificate
  Manager goes through one guarded, logged adapter
* Review every portal email the site tried to send

= Relationship to Certificate Manager =

This plugin owns courses, students, classes and enrolments itself. It reads the
Certificate Manager's certificate records to show and verify them, and writes to
them through a single guarded adapter when a certificate is issued - creating the
record if it does not exist. It never alters that plugin's schema, so replacing or
updating Certificate Manager cannot destroy the portal.

If Certificate Manager is deactivated, everything except certificates and the
Legacy data tools keeps working, and the affected screens say so rather than
erroring.

= Roles =

Two roles are created, `sp_student` and `sp_teacher`, each with the `read`
capability only. All real authorisation is checked in code against enrolment and
teacher assignment, because a capability alone cannot express "this student, this
class". Both roles are redirected away from `/wp-admin/` to their own dashboard.

= Files =

Teacher-uploaded documents are stored in the normal uploads tree but named with
32 random characters, so their paths cannot be guessed or enumerated. Reading a
file always goes through an authorisation check.

== Installation ==

1. Upload the `student-portal` folder to `wp-content/plugins/`, or install the zip through Plugins → Add New.
2. Activate the plugin. It creates its tables, both roles and the `/portal/` and `/teacher/` anchor pages.
3. Go to Settings → Reading and make sure a permalink structure other than "Plain" is set, then visit Settings → Permalinks to save once so the rewrite rules register. Visiting either `/portal/` or `/teacher/` also refreshes them.
4. Open Student Portal → Settings and set your pass mark and grade scale. Only grades the Certificate Manager accepts are saved.
5. Install and configure an SMTP plugin. Without one, portal emails do not arrive; every attempt is recorded in Student Portal → Email Log.
6. Add teachers, import students, create classes, enrol students.

== Frequently Asked Questions ==

= Do I need the Certificate Manager plugin? =

No. The portal is fully functional without it. You only lose the certificates tab and the Legacy data tools.

= Will updating Certificate Manager break the portal? =

No. The portal stores everything it owns in its own `wp_sp_*` tables. It writes to the certificate tables only when you issue a certificate, through one guarded adapter that never changes their schema.

= How do I delete a student's account? =

Student Portal → Student Accounts → Disable. That also resets the password, so the account cannot sign in. Deleting the portal plugin never deletes anything unless you explicitly opt in.

= How do I remove all portal data? =

Set the option `sp_delete_data_on_uninstall` to 1 before deleting the plugin. Otherwise uninstalling leaves the tables, the roles and the accounts in place.

== Changelog ==

= 0.2.0 =
* Assignments and exams: teachers build papers and timed exams from a new
  Assignments section — question bank with CSV import, multiple sets per
  exam (so students get different orders), publish/close/retake controls —
  and students attempt them from a Tasks section in their portal, with a
  live countdown, auto-submit, answers beamed to the server as they type,
  late submissions graded empty and results shown per attempt.
* The student portal is redesigned to the agreed mockup: a rounded app shell
  on a lavender canvas with a sidebar (brand, section nav, office contact
  card, signed-in user), the page title in the top bar, and a right rail
  carrying the profile card, month calendar and attendance-progress rings.
  The dashboard opens with hero course tiles and a course table showing
  start date, attendance and result; the screens collapse gracefully down
  to phone width with the sidebar as an off-canvas drawer.
* Teachers no longer mark their own attendance: the self-attendance card
  and its handler are removed — only an administrator (or the auto-absent
  run) records a teacher's attendance now.
* Settings → "Institute contact" (phone, email) feeds the sidebar contact
  card shown to students; the card stays hidden until a number or address
  is saved.

= 0.1.6 =
* Course outlines (Student Portal → Courses → a course → "Course outline"):
  an administrator lists the topics a course covers, in order, with an edit
  step for each. Every class teaching the course shows the same outline.
* Teachers tick topics as covered from a new Curriculum tab on the class
  page, with a covered/total meter; each class tracks its own progress and
  the tick records who marked it and when.
* Students get a Curriculum section in their portal (plus a button on each
  course card) showing the outline with a progress meter, covered dates and
  what is still to come — read-only, updated by their teachers.

= 0.1.5 =
* Shared portal calendar (Student Portal → Calendar): holidays and tests,
  global or per class, cancel class everywhere — no register can be opened
  on those dates, the auto-absent run skips them and sessions on them drop
  out of attendance percentages.
* Teachers mark their own attendance (present/absent/leave) from a card on
  their dashboard; an administrator can override any teacher for any date
  from the Teachers screen. When every assigned teacher is away on a date,
  the class is treated as cancelled for it.
* Daily auto-absent run (Settings → "Auto-absent after (days)", default 7,
  0 disables): for scheduled class days past the edit window it creates the
  register, marks any enrolled student with no record as absent — named
  `auto_absent` in the audit trail — and submits it, then submits any other
  stale open registers. Off days, holidays, tests and staff-less dates are
  never touched.
* The dashboard banner explains cancelled dates, the month calendar marks
  event days, and the class attendance tab says why a date is blocked.

= 0.1.4 =
* Teacher screens get more top padding, and the sidebar is now toggleable
  from a top-bar switch: on desktop it collapses out of the way (preference
  remembered per browser), on mobile it slides in as an off-canvas drawer
  with a backdrop, Escape-to-close and scroll lock.

= 0.1.3 =
* Teacher portal dashboard rebuilt to the agreed layout: a sticky sidebar
  (brand, section nav with active state, quick create-class CTA, signed-in
  user) beside a greeting bar with the pending-results bell, a status banner,
  recent registers table, top students, class list, and a right rail with
  profile, month calendar and today's class timeline. The create-class form
  moved from the dashboard to the Classes screen where it belongs.

= 0.1.2 =
* Login illustration ships as assets/images/login.webp (used by both auth
  screens), and the hero welcome heading keeps its white title styling
  regardless of the shell heading reset.

= 0.1.1 =
* Self-styled authentication screens: the login and teacher login render in
  their own document shell with token-based colours, floating labels and a
  submit button with proper busy/disabled states, so no theme stylesheet
  (Astra, Hello Elementor, ...) can lend its own form styles or palette.
* Portal components declare their own typography and control chrome at a
  specificity themes cannot override.

= 0.1.0 =
* Initial release: student and teacher portals, attendance with a submit/reopen
  window and full audit trail, results with configurable grade scale, course
  material with gated downloads, CSV import with dry-run preview, portal-owned
  courses, and certificate issuance that writes the record through one guarded
  adapter before the Certificate Manager renders it.