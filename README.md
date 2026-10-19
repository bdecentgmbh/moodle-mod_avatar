# Avatars - Moodle Module Plugin

The Avatar module is a powerful extension for Moodle that enables students to collect and upgrade virtual avatars. It enhances engagement, gamifies the learning experience, and provides visual identity to students within your courses. The plugin supports multiple avatar variants, tagging, and rich metadata for each avatar.

This plugin functions as a standard Moodle activity. You can add it to any course, or even place it on the front page to make avatars globally available.

## For Administrators

### Installation

1. Download the Avatar module from the Moodle plugins directory or this repository.
2. Log in to your Moodle site as an administrator.
3. Navigate to **Site administration > Plugins > Install plugins**.
4. Upload the downloaded plugin ZIP file.
5. Follow the prompts to complete the installation.

Alternatively, you may install the plugin manually:

1. Download the module from [GitHub](https://github.com/bdecent/moodle-mod_avatar) or the Moodle plugins directory.
2. Extract the contents into `/mod/avatar` in your Moodle installation.
3. Visit **Site administration > Notifications** to complete the plugin setup.
4. If using the Pro addon, extract it to `/mod/avatar/addon/pro`.

# Global Configuration

These settings control how avatars behave globally across your Moodle site. To access them, go to:

**Site administration > Plugins > Activity modules > Avatar**

From this panel, administrators can define visibility, avatar behavior, synchronization options, and default values for new avatar activities.

## General Settings

## 1. Profile Image Synchronization

When enabled, the system will automatically use a user's selected "Primary Avatar" as their Moodle profile picture upon collection or upgrade.

This setting ensures avatar usage is visually reflected across Moodle.

### Notes:
- Only the *primary* avatar is synced to the profile.
- Changing the avatar immediately updates the profile image.
- This does not prevent users from manually changing their profile picture.

## 2. Images for Tags

Allows you to upload custom images representing avatar **tags**. These icons are displayed in avatar galleries instead of plain text tag names.

Use visual icons to differentiate avatar themes such as:
- 🧠 Mindset
- 🧘‍♂️ Wellness
- 🚀 Achievements

### Notes:
- The image filename **must exactly match the tag name**, excluding the file extension.
  - Example: a tag `warrior` should be represented by a file named `warrior.png` or `warrior.svg`.
- Supported formats: `.png`, `.jpg`, `.gif`, `.svg`.

## Avatar Addon (Pro Feature)

## 1. Display Avatar in Popover Region
---

Displays a user's collected avatars next to the top-right notification bell, making the avatar visible site-wide.

Initially, only the primary avatar is visible. When the user hovers over it, the full collection is revealed in an expandable panel.

This is a great way to reinforce gamified identity across Moodle.

### Collection Limits

Configure how learners can collect avatars within an activity.

#### a. Collection Total Limit

Restricts the total number of users who can collect an avatar in a given activity instance.

#### b. Collection Limit Per User

Defines how many avatars a single user can collect in that activity.

#### c. Collection Limit Per Interval

Sets a maximum number of avatars a user can collect during a specific time interval (e.g., 5 avatars per day).

#### d. Collection Interval

Select the time frame (hour, day, week, or month) for interval-based limitations.

## 5. Automatic Avatar Assignment

Automatically assign avatars to users based on custom profile field values. This is also useful for assigning a default avatar to new users.

### User Profile Field Mapping

If the selected profile field value matches the **name or ID number** of an avatar, the system will automatically assign that avatar to the user.

#### Use Case:
- You create a profile field like "Learning Path" with values like `explorer` and `scientist`.
- You then create avatars with matching ID numbers.
- The system assigns avatars accordingly upon user creation or update.

---

### Set Initial Avatar

Automatically assigns an avatar (based on the mapping above) when:
- A new user account is created.
- A user does not yet have a profile picture.

Ideal for onboarding or ensuring all users start with an avatar.

## Managing Avatars

Navigate to:
**Site administration > Plugins > Activity modules > Avatar > Manage avatars**

### Creating Avatars

1. Click **Create avatar**.
2. Fill in the required fields:

    1. **Avatar Name** – Display name of the avatar.
    2. **ID Number** – A unique internal reference (optional).
    3. **Description** – A user-visible description.
    4. **Tags** – Used to organize and filter avatars.
    5. **Preview Image** – Displayed in the avatar gallery.
    6. **Number of Variants** – Total upgrade levels available.
    7. **Avatar Images** – One image per variant, shown in the avatar info page.
    8. **Avatar Thumbnails** – Used in profile picture (if enabled). If not provided, avatar variant image is used.
    9. **Secret Info** – Visible only after the avatar is collected.

### Pro Features for Avatars

If you have the Pro addon, you can also configure:

- **Course Categories** – Limit avatar availability to specific categories.
- **Include Subcategories** – Apply avatar available recursively from subcategories.
- **Cohorts** – Restrict visibility to selected cohorts.
- **Total Capacity** – Limit how many users may collect a specific avatar.

### Managing Existing Avatars

The management table provides options to:

- **Edit** – Modify metadata or images.
- **Toggle Status** – Enable or disable visibility.
- **Archive** – Move avatar to archive (non-collectable).
- **Export** – Download avatar package (Pro).
- **Delete** – Permanently remove (available only for archived avatars).

### Pro Features

#### Export & Import

- **Export Avatars** – Use the download icon to save avatars as ZIP files.
- **Import Avatars** – Click "Import Avatar" to upload ZIPs and restore avatars.

## Adding an Avatar Activity to a Course

1. Turn editing on in your course.
2. Click "Add an activity or resource" and select "Avatar" from the list.
3. Configure the activity settings:

    a. **General**

    1. **Name**: The name of your Avatar activity
    2. **Description**: A brief description of the activity


    b. **Appearance**

    1. **Display Mode**: Choose between "Page" (separate page) or "Inline" (embedded in the course page)
    2. **Header Content**: Add optional content to display at the top of the activity
    3. **Footer Content**: Add optional content to display at the bottom of the activity
    4. **Empty State**: Content to display when no avatars are available


    c. **Avatar Selection**

    1. **Avatar Selection**: Choose between "All avatars" or "Select specific avatars"
    2. **Specific Tags**: If selecting specific avatars, enter comma-separated tags in the "Specific tags" field. Then the activity will list avatars which are have the given tags.


    d. **Collection Limits**

    1. **Total Collection Limit**: Maximum number of avatars that can be collected across all users
    2. **Collection Limit Per User**: Maximum number of avatars a single user can collect
    3. **Collection Limit Per Interval**: Maximum number of avatars that can be collected during a specified time interval
    4. **Collection Interval**: Time period for the interval limit


    e. **Completion**

    1. **Require View**: Require students to view the activity to complete it
    2. **Require Avatar Collection**: Require students to collect at least one avatar to complete it


4. Click "Save and display" to create the activity.

### Viewing Student Progress

1. Open the avatar activity
2. Click **Report**
3. View students and their collected avatars
4. Use the "+" icon to manually assign or upgrade avatars through a modal

## Reports and Analytics

Navigate to:
**Site administration > Reports > Avatar Overview**

The Avatar module provides powerful reporting capabilities through two main report sources:

1. **Avatar Overview** - For analyzing avatar data and usage statistics
2. **User Avatars** - For tracking which users have collected which avatars

## Avatar Overview Report Source

The Avatar Overview report source provides information about avatars and their usage across your Moodle site.

### Available Fields

#### Avatar Information

- **Name**: The name of the avatar
- **ID Number**: The unique identifier assigned to the avatar
- **Description**: The full description text of the avatar
- **Status**: Whether the avatar is active or inactive
- **Variants**: The number of upgrade levels available for this avatar
- **Time Created**: When the avatar was first created
- **Time Modified**: When the avatar was last modified


#### Creator Information

- **Created By**: The name of the user who created the avatar
- **Created By (linked)**: The name of the creator with a link to their profile


#### Availability Information

- **Course Categories**: The course categories where this avatar is available
- **Cohorts**: The cohorts that have access to this avatar


#### Usage Statistics

- **Number of Courses**: How many courses include this avatar
- **Number of Users**: How many users have collected this avatar
- **Number of Activities**: How many avatar activities include this avatar
- **First Collected Time**: When the avatar was first collected by any user
- **First Collected User**: The first user to collect this avatar
- **Last Collected Time**: When the avatar was most recently collected
- **Last Collected User**: The most recent user to collect this avatar
- **Most Collected in Course**: The course where this avatar has been collected most frequently
- **Most Collected in Cohort**: The cohort with the highest number of collections for this avatar


### Available Filters

#### Basic Filters

- **Name**: Filter avatars by name (supports partial matching)
- **ID Number**: Filter by the avatar's ID number
- **Status**: Filter by active or inactive status
- **Time Created**: Filter by creation date (before, after, or between dates)

#### Availability Filters

- **Category**: Filter avatars by associated course category
- Includes option to include subcategories
- **Cohort**: Filter avatars by associated cohort

#### User-Related Filters

- **Created By**: Filter avatars by creator
- **Current User Avatar**: Show only avatars collected by the current user
- **My Cohorts**: Show only avatars available to cohorts the current user belongs to
- **My Users**: Show only avatars collected by users the current user mentors

## User Avatars Report Source

The User Avatars report source provides information about which users have collected which avatars.

### Available Fields

#### User Information

All the available columns from Users Entity like the following columns.

- **Full Name**: The user's full name
- **Email Address**: The user's email address
- **ID Number**: The user's ID number
- **Department**: The user's department
- **Institution**: The user's institution


#### Avatar Information

- **Avatar Name**: The name of the collected avatar
- **Variant**: The current variant level the user has reached
- **Time Collected**: When the user collected the avatar
- **Time Since Collected**: How long ago the avatar was collected
- **Primary Status**: Whether this is the user's primary avatar


### Available Filters

#### User Filters

- **Full Name**: Filter by user name
- **Email Address**: Filter by user email
- **ID Number**: Filter by user ID number
- And More

#### Avatar Filters

- **Avatar Name**: Filter by avatar name
- **Time Collected**: Filter by collection date
- **Primary Status**: Filter to show only primary avatars
- **Cohort**: Filter by cohort membership
- **Course**: Filter by course enrollment
- **Course Category**: Filter by course category

### Best Practices

1. **Integrate with course content**: Use avatars as rewards for completing modules or achieving certain milestones.
2. **Create themed collections**: Design avatar sets that relate to your course content or learning objectives.
3. **Use progressive disclosure**: Utilize the variant system to encourage continued engagement.
4. **Balance availability**: Set reasonable collection limits to maintain engagement without making avatars too easy or too difficult to obtain.
5. **Provide clear instructions**: Explain to students how they can collect and upgrade avatars.
6. **Use tags effectively**: Organize avatars with meaningful tags to help with filtering and selection.
7. **Leverage secret information**: Use the secret info feature to reveal additional content when students fully upgrade avatars.


## Troubleshooting

### Common Issues

1. **Avatars not displaying**: Check that the avatar images have been uploaded correctly and that the avatar status is set to active.
2. **Students cannot collect avatars**: Verify that the collection limits haven't been reached and that the avatar is available to the student's course category or cohort.
3. **Avatar images not showing**: Ensure that the file permissions are set correctly and that the images are in a supported format.
4. **Pro features not working**: Confirm that the Pro addon is installed correctly.

## FAQ

**Q: Can students use their collected avatars as profile pictures?**
A: Yes, if the "Profile image synchronization" setting is enabled, students can set their collected avatars as their profile pictures.

**Q: How many variants can an avatar have?**
A: An avatar can have up to 10 variants, allowing for progressive upgrades.

**Q: Can I restrict avatars to specific groups of students?**
A: Yes, with the Pro addon, you can restrict avatars to specific course categories and cohorts.

**Q: Can I track which students have collected which avatars?**
A: Yes, the activity report shows detailed information about student avatar collections.

**Q: How do students upgrade their avatars?**
A: After collecting an avatar, students can click the "Upgrade" button on the avatar's detail page. Upgrades may be subject to the same availability restrictions as initial collection.

**Q: Can I set different images for different avatar variants?**
A: Yes, each variant level can have its own image and thumbnail.

**Q: How do tag images work?**
A: Upload images to the tag images setting with filenames matching the tag names. These images will be displayed instead of the tag text where applicable.
