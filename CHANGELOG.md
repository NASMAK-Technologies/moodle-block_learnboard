# Changelog

All notable changes to LearnBoard for Moodle (block_learnboard).
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the version numbers follow [Semantic Versioning](https://semver.org).

## [1.1.19] - 2026-10-31

### Fixed
- When saving a connection code does not connect, the settings page now says
  why in words. A workspace that had not made a connection code yet was
  reported as "code_replaced", which suggested the code had been changed.

## [1.1.18] - 2026-10-31

### Fixed
- My learning, Certificate expiry, Mandatory training and the parent view no
  longer fail on sites that issue certificates with the Certificate manager
  (tool_certificate). LearnBoard reads the certificate's template name, and
  the data connector refused that table, so those views showed an error.
- On a course page, charts that can follow a course now show that course.
  The block handed the course over under a name the charts did not read, so
  they showed every course.

## [1.1.17] - 2026-10-27

### Fixed
- Saving a connection code now also switches on per-user sign-in, as the
  field says. On a newly installed site it stayed off, so teachers, managers
  and learners saw "could not load" in blocks and on the LearnBoard page,
  and only site managers saw charts, as one shared read-only view.

## [1.1.16] - 2026-10-23

### Changed
- Connect to LearnBoard now reads as three steps taken in order, one per row:
  get your connection code (with a link to LearnBoard), paste it in the box and
  save, then open your dashboards. Step 3 stays locked until the site connects,
  and once it is connected the first two steps show as done, with the date.

## [1.1.15] - 2026-10-17

### Fixed
- A block shown only to students or only to teachers now appears on the
  Dashboard and the front page. Students and teachers hold their roles inside
  courses, not where those pages sit, so the block was hidden from everyone
  there. On a course page the course's own roles still decide.

## [1.1.14] - 2026-10-14

Changes from the Moodle plugins directory review.

### Security
- The data connector's query check now denies by default. Only the
  reporting tables LearnBoard uses can be read; every other table, including
  every table that holds configuration, sessions or credentials, is refused.
  A FROM or JOIN the check cannot read completely is refused instead of
  skipped, MySQL's TABLE and VALUES statements are refused anywhere, and every
  identifier carrying the site's table prefix is checked wherever it appears.
- Columns can no longer be renamed past the password and token checks: column
  lists on WITH names and derived tables are refused, and so is SELECT *.

### Changed
- The data connector runs its queries through Moodle's DML API on a session
  set read-only, using Moodle's own connection or the optional SELECT-only
  database user. Its nonce, slot and log files use Moodle's directory
  helpers, the signature is checked before any query connection is opened,
  and the version it reports is read from version.php.
- Token renewal and layout saving are Moodle web services
  (block_learnboard_get_token, block_learnboard_save_layout) instead of the
  ajax/ scripts, which are removed.
- Requests to LearnBoard keep Moodle's HTTP security checks on. A LearnBoard
  on a private network is allowed in Site administration > Security > HTTP
  security. The settings page warns while "Allow self-signed SSL" is on.
- Installing no longer builds the whole admin tree to store the defaults.

### Fixed
- The "not set up yet" notice pointed to Local plugins; it now links to
  Plugins > Blocks > Connect to LearnBoard.

## [1.1.13] - 2026-10-10

### Added
- Each role can see something different in one block. Under "What each role
  sees", choose a role (custom roles such as a company manager role
  included) and what they see: My team, My learning, a LearnBoard dashboard,
  or nothing. The first rule whose role the viewer holds anywhere on the site
  applies.
- Two built-in views a block can show. My team: a teacher or company manager
  sees only the people in their own groups, in the courses they lead, with
  their progress, last visit, risk and completions. My learning: a learner
  sees their own progress. Both need per-user sign-in.
- "Show to" now lists every role on the site, custom roles included, and
  matches a role held anywhere on the site, so a block on the Dashboard
  reaches a company manager whose role sits in their courses.

### Changed
- Without per-user sign-in, the workspace-wide charts go only to people who
  may see LearnBoard across the whole site (a site administrator or a
  site-level manager). Before, a teacher holding the capability in their own
  course was shown every course and every company. Turn on per-user sign-in
  and teachers see their own groups instead.

## [1.1.12] - 2026-10-06

### Changed
- Supports Moodle 5.3. Tested on a Moodle 5.2 site upgraded to 5.3.0: the
  block, the LearnBoard page and the data connector work unchanged, and every
  LearnBoard screen reads the upgraded site without an error. Moodle 4.0 to
  5.3 in all. No code changes were needed.

## [1.1.11] - 2026-09-30

First public release.

### Added
- Connect a Moodle site to LearnBoard by pasting one connection code.
  LearnBoard tests the connection straight away. Once connected, the page shows this
  site's own dashboard link to open or copy, how to add it to a menu, and the
  LMS Overview dashboard every workspace starts with.
- The LearnBoard block: charts on course pages, the Dashboard and the front
  page, either arranged in the block or mirroring a dashboard built in
  LearnBoard. Charts are drawn in the page itself, not in an iframe. A new
  block starts with six headline figures and two charts; with editing on,
  Add charts offers LearnBoard's full catalogue.
- The LearnBoard page (`/blocks/learnboard/index.php`): your LearnBoard
  dashboards full width inside Moodle, with tabs, Refresh, full screen and a
  link into LearnBoard. On Moodle 4.3 and later it is added to the main menu.
- The data connector: LearnBoard reads reporting data through signed,
  read-only requests over the site's own HTTPS address, so the database needs
  no outside access. Every statement is checked before it runs and every
  request is logged.
- Per-user sign-in: a chart shows the signed-in person's own view, limited by
  their Moodle role, and nobody signs in twice.
- Moodle 4.0 to 5.2, PHP 7.3 or later. The data connector needs MySQL or
  MariaDB.
