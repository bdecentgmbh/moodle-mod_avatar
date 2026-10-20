---
exclude: true
---

# Avatar — Developer guide

How Avatar is built, for people changing it or extending it.

## Components

| Component | Directory | Purpose |
| --- | --- | --- |
| `mod_avatar` | `mod/avatar` | The activity and the avatar library (free, public) |
| `avataraddon_*` | `mod/avatar/addon/*` | Addons (subplugin type `avataraddon`) |

The free plugin does not know any addon. Addons extend it through the hooks in `classes/hook`
(Moodle Hooks API), the core callbacks every plugin can implement, and backup subplugin structures.
The paid addons are private repositories, wired into the public repository as git submodules; see
*Repositories and releases* below.

## Data

| Table | Content |
| --- | --- |
| `avatar` | The activities: avatar selection (all or specific tags), display mode, texts, completion rule |
| `avatar_list` | The avatar library (system context): name, ID number, texts, number of variants, status, archived |
| `avatar_user` | The collections: user, avatar, variant, primary flag, the activity it was collected or last upgraded in (`cmid`, 0 outside of activities) |

Files of the library are in the system context, component `mod_avatar`, item id = avatar id:
`previewimage`, `additionalmedia`, `description`, `secretinfo`, and per variant `avatarimageN`,
`avatarthumbnailN`, `animationstatesN` (N = 1 to `\mod_avatar\avatar::MAX_VARIANTS`). The texts of an
activity (`headercontent`, `footercontent`, `emptystate`) are in its module context, item id =
instance id.

## API

- `\mod_avatar\collection`: `get_user_avatar()`, `user_has_avatar($userid, $avatarid, $minvariant)`,
  `get_primary_avatar()`, `is_avatar_available()`, `get_activity_availability()`, `collect()`,
  `upgrade()`, `set_primary()`. It checks no capabilities; callers check that the current user may
  act for the given user. Collecting and upgrading trigger `avatar_collected` (or `avatar_assigned`
  when done for another user), `avatar_upgraded` and `avatar_completed`, sync the profile picture
  and update the completion of the activity.
- To collect or upgrade for a user, use `collection::collect_if_available()` and
  `collection::upgrade_if_available()`. They run the availability checks and the change under locks on
  the activity, the avatar and the user, so that limits and capacities hold for concurrent requests;
  they return null when the activity or the avatar is not available. Do not call them inside a
  database transaction. `collect()` and `upgrade()` change the collection without any check.
- `\mod_avatar\activity`: the avatars an activity offers (`get_avatars()`, `offers_avatar()`).
- `\mod_avatar\local\usage`: where an avatar is used.
- Web services `mod_avatar_collect_avatar` and `mod_avatar_upgrade_avatar` act for the current user
  only.

Plugins that gate access by avatars (for example an availability condition) use
`collection::user_has_avatar()` and observe the events above.

## Hooks

| Hook | Dispatched | Typical use |
| --- | --- | --- |
| `avatar_availability` | For each avatar shown in an activity, and before collecting or upgrading | Deny avatars (cohorts, categories, capacity) |
| `activity_availability` | When an activity is shown, and before collecting or upgrading | Deny collecting (limits) and add messages |
| `extend_mod_form`, `mod_form_validation`, `after_instance_saved`, `before_instance_deleted` | Activity form and lifecycle | Activity settings of an addon |
| `extend_avatar_form`, `avatar_form_validation`, `after_avatar_saved`, `before_avatar_deleted` | Avatar form of the library and lifecycle | Avatar settings of an addon |
| `extend_avatars_table` | Tables of *Manage avatars* | Columns and row actions |
| `extend_manage_avatars` | *Manage avatars* page | Buttons (e.g. import) |
| `extend_activity_navigation` | Settings navigation of an activity | Pages of an addon (e.g. a report) |

Register callbacks in the addon's `db/hooks.php`:

```php
$callbacks = [
    [
        'hook' => \mod_avatar\hook\activity_availability::class,
        'callback' => [\avataraddon_example\hook_callbacks::class, 'activity_availability'],
    ],
];
```

## Writing an addon

- Folder `mod/avatar/addon/<name>`, component `avataraddon_<name>`, with the usual `version.php`
  (depending on `mod_avatar`) and `lang/en/avataraddon_<name>.php`.
- `settings.php` fills `$settings`, an admin page in the Avatar category.
- Backup: `backup/moodle2/backup_avataraddon_<name>_subplugin.class.php` with
  `define_avatar_subplugin_structure()` (activity data) and/or
  `define_avatar_item_subplugin_structure()` (avatar data); the matching restore class.
- Store activity data by instance id and avatar data by avatar id; delete it in
  `before_instance_deleted` and `before_avatar_deleted`.

## Repositories and releases

- `moodle-mod_avatar` (public) is the free plugin. Its addons are private repositories,
  `moodle-avataraddon_<name>`, referenced as git submodules in `addon/<name>`. `.gitignore`
  ignores any other folder under `addon/`, and `.gitattributes` keeps the empty submodule
  placeholders out of source archives.
- The CI of `moodle-mod_avatar` runs without the submodules; each addon repository runs its own CI
  against `mod_avatar`.
- Releases are tagged `vX.Y-rN` (e.g. `v1.1-r1`). After publishing a release, the workflow *Stable
  branches* of `moodle-mod_avatar` (started by hand with the tag) builds its `MOODLE_xxx_STABLE`
  branches: the free plugin without the addon submodules, for sites that deploy from git.
- The private `moodle-mod_avatar_pro` repository only holds a publish workflow. It builds the
  bundle (mod_avatar with all addons inlined) from a tag of `moodle-mod_avatar` and commits it to
  its `MOODLE_xxx_STABLE` branches, with a release ZIP.

## Tests

PHPUnit tests are in `tests/`, Behat features in `tests/behat`. The data generator
(`tests/generator/lib.php`) creates activities, avatars (`create_avatar()`) and collected avatars
(`create_user_avatar()`); in Behat use `the following "mod_avatar > avatars" exist` and
`the following "mod_avatar > collected avatars" exist`.
