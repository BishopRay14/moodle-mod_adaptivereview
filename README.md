# Adaptive Review for Moodle

Adaptive Review is an experimental Moodle 5 activity module designed around mastery-based retrieval practice rather than traditional Leitner boxes.

Rather than treating flashcards as fixed-box objects, Adaptive Review tracks individual card mastery and is designed to support reusable card repositories, instructor-created card sets, and adaptive scheduling based on long-term memory research.

## Features

Current prototype includes:

- Modern Vue 3 dashboard
- Individual card mastery tracking
- Persistent review-session and attempt history
- Global card identities and reusable repository sets
- Lifelong student-card mastery shared across course activities
- Activity-specific Set Mastery goals
- Set Mastery progress dashboard
- Instructor-defined mastery goals
- Searchable All Cards library
- Retrieval-first review workflow
- "I Know It" / "Not Yet" review interface
- Recently Learned browse and practice modes
- Responsive user interface

## Planned Features

The long-term vision includes:

- Time-based adaptive scheduling engine
- Shared institutional content libraries
- CSV import/export
- Analytics dashboard
- Instructor authoring tools
- Advanced mastery algorithms
- Moodle gradebook integration based on instructor Goals

## Technology

- Moodle 5.0 or later
- PHP
- Vue 3
- Tailwind CSS
- Vite

## Installation

1. Copy this plugin into:

   ```
   moodle/mod/adaptivereview
   ```

2. Visit the Moodle Site Administration notifications page.

3. Complete the upgrade process.

4. Configure the activity inside a Moodle course.

## Testing

The automated suite uses Moodle's isolated PHPUnit prefix and dataroot. It must
never use the live `m_` table prefix or `/var/www/moodledata` directory.

For this Moodle Docker checkout, initialize and run the plugin suite from the
webserver container:

```bash
cd /var/www/html
composer install --no-interaction
php public/admin/tool/phpunit/cli/init.php --disable-composer
vendor/bin/phpunit --testsuite mod_adaptivereview_testsuite
```

The local `config.php` must define a non-production `$CFG->phpunit_prefix` and
`$CFG->phpunit_dataroot`. The current Docker configuration uses `t_` and
`/var/www/phpunitdata` respectively.

The GitHub Actions matrix covers the minimum Moodle 5.0 release and current
Moodle 5.2 and 5.3 lines on supported PHP and PostgreSQL versions.

## Current Status

Adaptive Review is currently under active development.

The current prototype includes the global repository, reusable sets,
course-integrated set selection, lifelong student-card mastery, the
`mastery-v1` scheduler, the authoritative student dashboard, and Moodle
privacy and backup/restore lifecycle coverage. The next phase is the MVP
release-candidate audit and administrator documentation.

## License

Adaptive Review is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.

This plugin is distributed under the same GPL-compatible licensing model as Moodle.

See the [LICENSE](LICENSE) file for the full license text.

---

Developed by Briley Ray
