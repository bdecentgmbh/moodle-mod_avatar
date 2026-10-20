# Avatar

Avatar is a Moodle activity in which participants collect avatars and upgrade them through their
variants. The avatars come from one avatar library for the whole site; each activity offers all of
them or those with certain tags. A participant's collection appears on their *My avatars* page, and
their primary avatar can become their profile picture.

## Features

- **Avatar library**: avatars with a preview image, up to ten variants with images, thumbnails and
  animation states, additional media, a description, secret information revealed once collected,
  and tags (optionally shown as images).
- **Avatar activity**: offers all avatars or those with specific tags, on its own page or directly
  on the course page, with header, footer and empty-state texts.
- **Collecting and upgrading**: participants pick avatars and upgrade them variant by variant.
- **My avatars**: the collection of a user, with the choice of the primary avatar.
- **Profile picture sync**: optionally, the primary avatar becomes the profile picture.
- **Activity completion**: by viewing the activity or by collecting an avatar in it.
- **Backup and restore**, **privacy API**, **Moodle 4.5 to 5.3**.
- **Addons**: subplugins (`mod/avatar/addon`) extend the activity through hooks.

## Requirements

- Moodle 4.5 to 5.3

## Installation

Install it like any other activity module, into `mod/avatar`, then visit *Site administration ▸
Notifications*. To deploy from git, use the `MOODLE_xxx_STABLE` branch of your Moodle version (for example
`MOODLE_405_STABLE`); `main` references the paid addons as git submodules.

## Documentation

- [Administrator guide](docs/admin-guide.md)
- [Manager guide](docs/manager-guide.md): the avatar library
- [Teacher guide](docs/teacher-guide.md)
- [Student guide](docs/student-guide.md)
- [Developer guide](docs/developer-guide.md): hooks, API, addons

## License

2025 bdecent GmbH <https://bdecent.de>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.
