define([
    "jquery",
    "core/modal_factory",
    "core/modal_events",
    "core/fragment",
    "core/ajax",
    "core/notification",
    "core/str",
], ($, ModalFactory, ModalEvents, Fragment, Ajax, Notification, Str) => {

    var SELECTORS = {
        ASSIGN_AVATAR_BTN: '.avatar-assign-btn',
        UPGRADE_AVATAR_BTN: 'table#avatar_report td a.avatar-upgrade-btn',
    };


    /**
     * Initialize the modal
     *
     * @param {int} cmid Course module ID
     * @param {int} userid User ID
     * @return {Promise}
     */
    var assignAvatar = (cmid, userid) => {
        // Load the modal fragment.
        return ModalFactory.create(
            {
                type: ModalFactory.types.DEFAULT,
                title: Str.get_string("assignnewavatar", "mod_avatar"),
                body: Fragment.loadFragment("mod_avatar", "available_avatars", 1, {cmid: cmid, userid: userid}),
                large: true,
            }
        ).then((modal) => {
            modal.show();
            // Handle form submission
            modal.getRoot().on("click", ".assign-avatar-btn", (e) => {
                e.preventDefault();
                var form = e.currentTarget.querySelector("form");
                // Submit form via AJAX
                Ajax.call([
                    {
                        methodname: "mod_avatar_collect_avatar",
                        args: {
                            avatarid: form.querySelector('input[name="avatarid"]')?.value,
                            cmid: cmid,
                            userid: userid,
                        },
                        done: (response) => {
                            if (response.success) {
                                // Reload page to show updated avatar
                                window.location.reload();
                            } else {
                                Notification.addNotification({
                                    message: response.message,
                                    type: "error",
                                });
                            }
                        },
                        fail: Notification.exception,
                    },
                ]);
            });

            modal.getRoot().on('click', '.upgrade-avatar-btn', (e) => {
                e.preventDefault();
                var form = e.currentTarget.querySelector("form");
                // Submit form via AJAX
                Ajax.call([
                    {
                        methodname: "mod_avatar_upgrade_avatar",
                        args: {
                            avatarid: form.querySelector('input[name="avatarid"]')?.value,
                            cmid: cmid,
                            userid: userid,
                        },
                        done: (response) => {
                            if (response.success) {
                                // Reload page to show updated avatar
                                window.location.reload();
                            } else {
                                Notification.addNotification({
                                    message: response.message,
                                    type: "error",
                                });
                            }
                        },
                        fail: Notification.exception,
                    },
                ]);
            });

            return modal;
        });
    };

    var init = (cmid) => {

        // Handle avatar assignment
        document.querySelector(SELECTORS.ASSIGN_AVATAR_BTN)?.addEventListener('click', (e) => {
            e.preventDefault();
            var userid = e.currentTarget.dataset.userid;
            assignAvatar(cmid, userid);
        });

        // Upgrade the avatar on click.
        document.querySelector(SELECTORS.UPGRADE_AVATAR_BTN)?.addEventListener('click', (e) => {
            e.preventDefault();
            var userid = e.currentTarget.dataset.userid;
            var avatarid = e.currentTarget.dataset.avatarid;

            // Submit form via AJAX
            Ajax.call([
                {
                    methodname: "mod_avatar_upgrade_avatar",
                    args: {
                        avatarid: avatarid,
                        cmid: cmid,
                        userid: userid,
                    },
                    done: (response) => {
                        if (response.success) {
                            // Reload page to show updated avatar
                            window.location.reload();
                        } else {
                            Notification.addNotification({
                                message: response.message,
                                type: "error",
                            });
                        }
                    },
                    fail: Notification.exception,
                },
            ]);
        });
    };

    var pickAvatar = function() {

        const selector = '.collect-avatar, .upgrade-avatar';

        document.querySelectorAll(selector)?.forEach(
            (element) => element.addEventListener('click', function(e) {
                e.preventDefault();

                var avatarId = e.currentTarget.dataset.avatarid;
                var cmid = e.currentTarget.dataset.cmid;

                var action = e.currentTarget.classList.contains('collect-avatar') ? 'collect' : 'upgrade';
                var methodName = action === 'collect' ? 'mod_avatar_collect_avatar' : 'mod_avatar_upgrade_avatar';

                Ajax.call([{
                    methodname: methodName,
                    args: {avatarid: avatarId, cmid: cmid},
                    done: function(response) {
                        Notification.addNotification({
                            message: response.message,
                            type: response.success ? 'success' : 'error'
                        });
                        if (response.success) {
                            window.location.reload();
                        }
                    },
                    fail: Notification.exception
                }]);
            })
        );
    };

    return {
        init: init,
        pickAvatar: pickAvatar,
    };
});
