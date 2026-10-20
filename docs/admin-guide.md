---
pagetypes:
  - mod-avatar-*
  - admin-mod-avatar-*
  - admin-setting-modsettingavatar
---

# Avatar — Administrator guide

Avatar is an activity in which participants collect avatars from a site-wide avatar library and
upgrade them through their variants. This guide covers installing and configuring Avatar, the
permissions and what happens on backup and restore. For the library itself see the
[manager guide](manager-guide.md), for the activity the [teacher guide](teacher-guide.md).

## Requirements

- Moodle 4.5 to 5.3.

## Installation

Install it like any other activity module: upload the ZIP under *Site administration ▸ Plugins ▸
Install plugins*, or put the code into `mod/avatar` and visit *Site administration ▸
Notifications*.

## Settings

*Site administration ▸ Plugins ▸ Activity modules ▸ Avatar ▸ General settings*

| Setting | Effect |
| --- | --- |
| Profile picture sync | When a user collects, upgrades or chooses a primary avatar, its image becomes the user's profile picture. Users can still change their picture afterwards. |
| Display mode | The default display mode of new avatar activities: on the activity page, or directly on the course page. |
| Images for tags | Images shown instead of the tag names on the avatar page. The file name without its extension must be the tag name, for example `dragons.png` for the tag *dragons*. |

## The avatar library

The avatars are created once for the whole site, under *Site administration ▸ Plugins ▸ Activity
modules ▸ Avatar ▸ Manage avatars*, and every avatar activity offers all of them or those with
certain tags. Users with the capabilities below can open the page without being site
administrators, from the same address (`/mod/avatar/manage.php`).

## Capabilities

| Capability | Default | Allows |
| --- | --- | --- |
| `mod/avatar:addinstance` | Teacher, Manager | Adding avatar activities |
| `mod/avatar:view` | Authenticated user, Guest | Seeing avatar activities, avatars and *My avatars* |
| `mod/avatar:collect` | Student | Picking avatars in an activity |
| `mod/avatar:upgrade` | Student | Upgrading own avatars in an activity |
| `mod/avatar:overview` | Manager | Opening *Manage avatars*, including where avatars are used and by whom |
| `mod/avatar:create`, `mod/avatar:edit` | Manager | Creating and editing avatars of the library |
| `mod/avatar:changestatus` | Manager | Enabling and disabling avatars |
| `mod/avatar:archive` | Manager | Archiving and restoring avatars |
| `mod/avatar:delete` | Manager | Deleting archived avatars, including them in the users' collections |
| `mod/avatar:manage` | Manager | Changing the availability settings that addons add to avatars |

The library capabilities are checked in the system context, the activity capabilities in the
activity, so they can be overridden per activity.

## Backup and restore

- An activity backup contains the avatars the activity offers or that were collected in it.
  Their texts and images are only included when the user making the backup may create avatars;
  otherwise only their names and ID numbers.
- On restore, avatars that already exist (same ID number, or same name when the avatar has no ID
  number) are used as they are and never changed.
- Avatars that do not exist are only created when the user restoring may create avatars
  (`mod/avatar:create`) and the backup contains their content. Only images (and videos in the
  additional media) are restored into their image fields.
- With user data, the avatars that users collected in the activity are added to their collections.

## Privacy

A user's avatar collection belongs to the user, not to the activity in which an avatar was
collected. It is exported and deleted with the user's data. For the avatars of the library, the
user who changed them last is recorded; on a deletion request this reference is removed and the
avatar stays.

## Addons

Addons extend Avatar from `mod/avatar/addon`, as subplugins of the type *Avatar addon*. Their
settings appear in *Site administration ▸ Plugins ▸ Activity modules ▸ Avatar*. The free plugin
works without any addon.
