# Changelog

## 1.1 (2026-10-20)

First public release; supports Moodle 4.5 to 5.3.

- The free plugin now contains the core of the former pro edition, including its fixes: completion
  by course module, file items of the activity texts, cleanup when avatars are deleted.
- Addons extend the activity through hooks (`classes/hook`); the paid features are separate addons.
- Security: the web services act for the current user only and accept only avatars the activity
  offers; *My avatars* shows the own collection only and changes it with POST and sesskey; file,
  fragment and table access checks; escaped avatar names everywhere; restore creates avatars only
  for users who may manage the library.
- Managers can use the avatar library pages, which were limited to site administrators.
- Privacy provider for collections and avatar authors, with user lists.
- Backups contain only the avatars of the activity.
- Documentation for the Documentation Center (`docs/`).
