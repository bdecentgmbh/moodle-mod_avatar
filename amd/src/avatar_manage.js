define(["jquery", "core/modal_factory", "core/modal_events",
    "core/ajax", "core/templates", "core/str", "core/fragment", "core/notification"], (
    $, ModalFactory, ModalEvents, Ajax, Templates, Str, Fragment, Notification) => {

    var AvatarUsageModal = {

        init: function() {
            document.body.addEventListener("click", (e) => {
                if (e.target.matches(".usage-item")) {
                    this.showUsageModal(e);
                }
            });
        },

        showUsageModal: function(e) {
            e.preventDefault();

            var type = e.target.dataset.type;
            var avatarId = e.target.dataset.avatarid;
            var title = e.target.closest('ul').dataset.title;
            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: Fragment.loadFragment("mod_avatar", "avatar_usage", 1, {type: type, avatarid: avatarId}),
            }).then((modal) => {
                modal.show();
                return modal;
            }).catch(Notification.exception);
        },
    };

    return AvatarUsageModal;
});

