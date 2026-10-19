<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_avatar\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

use core\output\html_writer;
use mod_avatar\hook\extend_avatars_table;

/**
 * Base of the avatar tables of the Manage avatars page (active and archived avatars).
 *
 * Addons add columns and row actions through the hook {@see extend_avatars_table}.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class avatars_table extends \table_sql {
    /** @var \core\context\system The context of the page. */
    protected $context;

    /** @var extend_avatars_table The dispatched hook with the columns and actions of the addons. */
    protected $hook;

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique id of the table.
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        $this->context = \core\context\system::instance();
        $this->hook = new extend_avatars_table($this->get_tab(), $this->context);
        \core\di::get(\core\hook\manager::class)->dispatch($this->hook);

        $columns = $this->get_table_columns();
        $addoncolumns = [];
        foreach ($this->hook->get_columns() as $name => [$header]) {
            $addoncolumns[$name] = $header;
        }
        // The columns of the addons go before the last columns (usage, actions).
        $position = max(0, count($columns) - $this->get_trailing_columns());
        $columns = array_slice($columns, 0, $position, true) + $addoncolumns + array_slice($columns, $position, null, true);

        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));
        $this->no_sorting('actions');
        foreach (array_keys($addoncolumns) as $name) {
            $this->no_sorting($name);
        }
        $this->collapsible(false);
    }

    /**
     * The tab of the table: 'active' or 'archive'.
     *
     * @return string
     */
    abstract protected function get_tab(): string;

    /**
     * The columns of the table: name => header.
     *
     * @return string[]
     */
    abstract protected function get_table_columns(): array;

    /**
     * How many of the last columns stay after the columns of the addons.
     *
     * @return int
     */
    protected function get_trailing_columns(): int {
        return 1;
    }

    /**
     * Name of the avatar.
     *
     * @param \stdClass $row The avatar.
     * @return string
     */
    public function col_name($row) {
        return format_string($row->name, true, ['context' => $this->context]);
    }

    /**
     * Description of the avatar.
     *
     * @param \stdClass $row The avatar.
     * @return string
     */
    public function col_description($row) {
        $description = file_rewrite_pluginfile_urls(
            (string) $row->description,
            'pluginfile.php',
            $this->context->id,
            'mod_avatar',
            'description',
            $row->id
        );
        return html_writer::div(
            format_text($description, $row->descriptionformat, ['context' => $this->context]),
            'avatar-description-table'
        );
    }

    /**
     * The columns of the addons, and the actions they add to the actions column.
     *
     * @param string $column The column name.
     * @param \stdClass $row The avatar.
     * @return string|null
     */
    public function other_cols($column, $row) {
        $columns = $this->hook->get_columns();
        if (isset($columns[$column])) {
            return (string) call_user_func($columns[$column][1], $row);
        }
        return parent::other_cols($column, $row);
    }

    /**
     * The actions added by the addons for a row.
     *
     * @param \stdClass $row The avatar.
     * @return string[] HTML of the action icons.
     */
    protected function get_addon_actions(\stdClass $row): array {
        return array_filter(array_map(fn($callback) => (string) $callback($row), $this->hook->get_actions()));
    }

    /**
     * An action icon that changes the avatar (with sesskey).
     *
     * @param \stdClass $row The avatar.
     * @param string $action The action of edit.php.
     * @param string $icon The pix icon.
     * @param string $label The label.
     * @param string|null $confirm The confirmation question, if any.
     * @return string HTML
     */
    protected function action_icon(\stdClass $row, string $action, string $icon, string $label, ?string $confirm = null): string {
        global $OUTPUT;

        $url = new \moodle_url('/mod/avatar/edit.php', ['id' => $row->id, 'action' => $action, 'sesskey' => sesskey()]);
        $confirmaction = $confirm ? new \core\output\actions\confirm_action($confirm) : null;
        return $OUTPUT->action_icon(
            $url,
            new \core\output\pix_icon($icon, $label),
            $confirmaction,
            ['class' => 'action-' . $action . ' action-icon']
        );
    }

    /**
     * Message when there are no avatars.
     */
    public function print_nothing_to_display() {
        global $OUTPUT;

        $notification = new \core\output\notification(
            get_string('avatarsnothingtodisplay', 'mod_avatar'),
            \core\output\notification::NOTIFY_INFO
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }
}
