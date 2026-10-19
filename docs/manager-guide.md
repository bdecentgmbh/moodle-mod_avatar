---
pagetypes:
  - admin-mod-avatar-*
  - mod-avatar-manage
  - mod-avatar-edit
  - mod-avatar-view_avatar
---

# Avatar — Manager guide

The avatars of all avatar activities come from one avatar library for the whole site. This guide
explains how to fill and maintain it. You need the capabilities described in the
[administrator guide](admin-guide.md); managers have them by default.

## Opening the library

*Site administration ▸ Plugins ▸ Activity modules ▸ Avatar ▸ Manage avatars*. If you are not a site
administrator, open `/mod/avatar/manage.php` directly.

The page has two tabs: **Active avatars** and **Archived avatars**. The *Usage* column shows in how
many activities and courses an avatar is offered and how many users collected it; click a number to
see the list.

## Creating an avatar

1. Click **Create avatar**.
2. Enter the **Avatar name** and, optionally, an **ID number**. The ID number must be unique; it is
   used to recognise the avatar when activities are restored from a backup.
3. Optionally enter a **Description**, **Internal notes** (only shown to you) and **Secret
   information**, which users see once they have collected the avatar.
4. Under **Gallery**, upload the **Preview image**, shown before the avatar is collected, and
   optionally **Additional media** (images or videos).
5. Under **Media**, choose the number of **Variants**. The form then shows, for each variant, an
   **Avatar image** (required), an optional **Avatar thumbnail** (used in galleries and as profile
   picture) and optional **Animation states** (more images of that variant).
6. Add **Tags**. Activities can offer only the avatars with certain tags.
7. Save.

## Variants

Users collect the first variant of an avatar and upgrade it to the next one, up to the last. Each
upgrade shows the image of the new variant; once an avatar is collected, its secret information is
revealed.

## Status, archive and delete

- **Disable** (eye icon): the avatar is no longer offered in activities. Users keep it.
- **Archive**: the avatar moves to *Archived avatars* and is no longer offered. Users keep it.
- **Restore**: an archived avatar becomes active again.
- **Delete** (only archived avatars): the avatar, its images and tags are deleted and it is removed
  from all collections. This cannot be undone.

## Tags and tag images

Tags group avatars, for example *dragons* or *christmas*. An administrator can upload an image per
tag (see the administrator guide); the avatar page then shows the image instead of the tag name.
