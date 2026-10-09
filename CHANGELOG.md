# Changelog

All notable changes to LearnBoard for Moodle (block_learnboard).
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the version numbers follow [Semantic Versioning](https://semver.org).

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
