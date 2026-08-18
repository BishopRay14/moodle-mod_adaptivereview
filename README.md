# Adaptive Review for Moodle

Adaptive Review is an experimental Moodle 5 activity module designed around mastery-based retrieval practice rather than traditional Leitner boxes.

Rather than treating flashcards as fixed-box objects, Adaptive Review tracks individual card mastery and is designed to support reusable card repositories, instructor-created card sets, and adaptive scheduling based on long-term memory research.

## Features

Current prototype includes:

- Modern Vue 3 dashboard
- Individual card mastery tracking
- Persistent review-session and attempt history
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
- Global card repository
- Reusable card sets across multiple courses
- Shared institutional content libraries
- CSV import/export
- Analytics dashboard
- Instructor authoring tools
- Advanced mastery algorithms
- Moodle gradebook integration based on instructor Goals

## Technology

- Moodle 5.x
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

## Current Status

Adaptive Review is currently under active development.

The current version demonstrates the user interface, mastery workflow, and activity structure. The adaptive scheduling engine is planned for a future development phase.

## License

Adaptive Review is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.

This plugin is distributed under the same GPL-compatible licensing model as Moodle.

See the [LICENSE](LICENSE) file for the full license text.

---

Developed by Briley Ray
