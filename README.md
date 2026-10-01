# LearnBoard for Moodle (block_learnboard)

Connects a Moodle site to [LearnBoard](https://www.learnoow.com/moodle-plugin), which turns
the site's own data into ready-made dashboards and reports: completions,
compliance, learners at risk, course effectiveness, cohorts. One plugin does
all of it.

**Charts on your pages.** Add the LearnBoard block to a course page, the
Dashboard or the front page. It shows the charts you arrange in it, or mirrors
a dashboard you built in LearnBoard. The charts are drawn in the page itself,
not in an iframe, so they take the site's theme and stay readable on a phone.

**A LearnBoard page inside Moodle.** A full-width view of your LearnBoard
dashboards at `blocks/learnboard/index.php`, reached from Moodle's main menu.

**It answers LearnBoard's questions.** LearnBoard sends signed, read-only
queries to `blocks/learnboard/query.php` over the HTTPS address the site
already serves, and the plugin answers with rows. The database never accepts a
connection from outside the server, so it can stay bound to localhost with no
port opened to anyone.

**It signs people in.** A chart is rendered for the person already logged in
to Moodle, with a short-lived token, so nobody signs in twice and nobody sees
more than their Moodle role allows.

## Requirements

- Moodle 4.0 to 5.2
- PHP 7.3 or later, within the range your Moodle version supports
- The automatic main-menu entry needs Moodle 4.3 or later (see *The menu entry*)
- A LearnBoard workspace. The free plan is all this plugin needs: https://www.learnoow.com/moodle-plugin
- MySQL or MariaDB for the data connector (see *Database support*)

## Database support

The plugin installs and runs on every database Moodle supports. The data
connector, the part that lets LearnBoard read your site's reporting data,
supports **MySQL and MariaDB only**: LearnBoard's reports are written for
them. On a PostgreSQL or Microsoft SQL Server site the connector answers
"unsupported database" and LearnBoard says so when you connect, so no
figures can be read from that site yet.

## Installing

1. Install the ZIP through *Site administration > Plugins > Install plugins*,
   or copy this folder to `blocks/learnboard`.
2. Visit *Site administration > Notifications* to finish the install.
3. In LearnBoard, go to *Admin > Data sources*, add a Moodle plugin source, and
   copy the **connection code** it shows you.
4. In Moodle, open *Site administration > Plugins > Blocks > Connect to
   LearnBoard*, paste the code, and save.

That is the whole setup. LearnBoard tests the connection immediately and tells
you if anything is wrong.

## The menu entry

The LearnBoard page lives at `/blocks/learnboard/index.php`. How it reaches
the main menu depends on the Moodle version:

| Moodle version | Main-menu entry |
| --- | --- |
| 4.3 to 5.2 | Added automatically for people allowed to see LearnBoard at site level. Turn it off with *Show LearnBoard in the main menu*. |
| 4.0 to 4.2 | Not added automatically. Add `LearnBoard\|/blocks/learnboard/index.php` to *Custom menu items* under *Site administration > Appearance*. |

Some themes draw their own navigation and choose its items themselves. On
those, add a link to `/blocks/learnboard/index.php` in the theme's own
settings.

## Where the charts come from

The charts are drawn by a script the plugin loads from your LearnBoard, at
`<LearnBoard address>/api/v1/embed/sdk.js` with its styles at `sdk.css`, the
same server the charts already take their figures from. The plugin itself
carries no compiled code, and a chart fix in LearnBoard reaches the site
without a plugin upgrade. Browsers keep the script for five minutes. The
script's source is part of LearnBoard.

## What it will and will not run

Every statement LearnBoard sends is checked by this plugin before it reaches
the database, on the site's own terms rather than LearnBoard's:

- reads only: no write, no schema change, no lock, no delay, no file access
- this site's tables only: no other database on the server, no `mysql`, no
  `performance_schema`
- no secrets: password hashes, tokens, session keys and gateway credentials are
  refused by name and dropped from results even when a query said `SELECT *`
- one statement per request, with bound parameters

The full list of refusals is the test suite: `tests/validator_test.php`, which
is also runnable without a Moodle install as
`php cli/validator_attack_check.php`.

Every request is written to `{dataroot}/block_learnboard/query-audit.log`, so
the site administrator can see exactly what was asked and when.

## Settings

| Setting | What it does |
| --- | --- |
| Connection code | Paste the code LearnBoard shows under *Data sources*. It carries the address and the shared secret, so there is nothing else to type. |
| Allow LearnBoard to read data through the plugin | The master switch for the data connector. Off, and `query.php` answers nothing. |
| LearnBoard server addresses | Optional. Restricts which IP addresses may ask at all. |
| Requests to answer at once | How many LearnBoard queries this site will run concurrently. Lower it if the site is busy. |
| Enable per-user sign-in (SSO) | Lets somebody already signed in to Moodle open LearnBoard without signing in again. |
| Show LearnBoard in the main menu | Adds the LearnBoard page to Moodle's main menu (Moodle 4.3 and later). |
| Allow self-signed SSL | For a development or on-premises install with its own certificate. A production site leaves this off. |

## Using the block

1. Turn editing on in a course or on the Dashboard.
2. *Add a block > LearnBoard*.
3. In the block's settings, choose **Linked to a LearnBoard dashboard** to
   mirror one you built in LearnBoard, or build a layout in the block itself.
   A dashboard appears in the list when, in LearnBoard, it is shared with
   **Moodle Embed (read-only)** or published to the whole workspace.

## Privacy

The plugin stores no personal data of its own. It sends the signed-in user's
identity to LearnBoard when per-user sign-in is on, and answers LearnBoard's
questions about the data already in the site, limited as described above. A
viewer's browser loads the chart script and the figures from LearnBoard, so
LearnBoard sees the viewer's IP address and browser, as any website does. See
`classes/privacy/provider.php`.

## Support

- Issues and source: https://github.com/NASMAK-Technologies/moodle-block_learnboard
- Documentation: https://www.learnoow.com/moodle-plugin
- Email: info@nasmak.com.au

## Licence

GPL v3 or later, the same as Moodle. See [LICENSE](LICENSE).
