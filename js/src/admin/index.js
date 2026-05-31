import Extend from 'flarum/common/extenders';
import { extend } from 'flarum/common/extend';
import app from 'flarum/admin/app';
import FormGroup from 'flarum/common/components/FormGroup';
import SupportModal from './components/SupportModal';
import TagPermissionTree from './components/TagPermissionTree';
import BypassGroupPicker from './components/BypassGroupPicker';

// Add Flarum's standard `Button--inverted` to the Cancel button in core's
// "Reset extension settings" modal so it doesn't render as a plain
// borderless button. We use a MutationObserver instead of extending the
// modal's prototype because the modal class is lazy-loaded by core and
// not statically importable through `flarum/admin/components/...` at
// module load. Each TryHackX extension registers this independently;
// repeated classList.add of the same class is a no-op.
app.initializers.add('tryhackx-topic-rating-cancel-inverted', () => {
    const invertCancel = (modal) => {
        const cancel = modal.querySelector('.Form-controls .Button:not(.Button--danger):not(.Button--primary)');
        if (cancel) cancel.classList.add('Button--inverted');
    };
    const observer = new MutationObserver((mutations) => {
        for (const mut of mutations) {
            for (const node of mut.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.classList && node.classList.contains('ResetExtensionSettingsModal')) {
                    invertCancel(node);
                } else if (node.querySelectorAll) {
                    node.querySelectorAll('.ResetExtensionSettingsModal').forEach(invertCancel);
                }
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
});

app.initializers.add('tryhackx-topic-rating-support', () => {
    app.registry.for('tryhackx-topic-rating').registerSetting(function () {
        return m('div', { className: 'TopicRating-support' }, [
            m('button', {
                className: 'Button',
                onclick: () => app.modal.show(SupportModal),
            }, [
                m('i', { className: 'fas fa-heart Button-icon icon' }),
                app.translator.trans('tryhackx-topic-rating.admin.support.button'),
            ]),
        ]);
    });
});

// Register custom FormGroup field type used by the tag-config setting below.
// Harmless when Tags isn't installed (just nothing references this type).
app.initializers.add('tryhackx-topic-rating-custom-fields', () => {
    extend(FormGroup.prototype, 'customFieldComponents', function (items) {
        items.add('tryhackx-topic-rating.tag-permission-tree', (attrs) => {
            return m(TagPermissionTree, Object.assign({}, attrs, { settingValue: attrs.bidi }));
        });
        items.add('tryhackx-topic-rating.bypass-group-picker', (attrs) => {
            return m(BypassGroupPicker, Object.assign({}, attrs, { settingValue: attrs.bidi }));
        });
    });
});

const tagsOn = !!(typeof flarum !== 'undefined' && flarum.extensions && flarum.extensions['flarum-tags']);

const adminExtender = new Extend.Admin()
    .setting(() => ({
        setting: 'tryhackx-topic-rating.enabled',
        label: app.translator.trans('tryhackx-topic-rating.admin.settings.enabled_label', {}, true),
        help: app.translator.trans('tryhackx-topic-rating.admin.settings.enabled_help', {}, true),
        type: 'boolean',
    }))
    .setting(() => ({
        setting: 'tryhackx-topic-rating.show_on_list',
        label: app.translator.trans('tryhackx-topic-rating.admin.settings.show_on_list_label', {}, true),
        help: app.translator.trans('tryhackx-topic-rating.admin.settings.show_on_list_help', {}, true),
        type: 'boolean',
    }))
    .setting(() => ({
        setting: 'tryhackx-topic-rating.rate_on_list',
        label: app.translator.trans('tryhackx-topic-rating.admin.settings.rate_on_list_label', {}, true),
        help: app.translator.trans('tryhackx-topic-rating.admin.settings.rate_on_list_help', {}, true),
        type: 'boolean',
    }))
    .setting(() => ({
        setting: 'tryhackx-topic-rating.allow_unactivated',
        label: app.translator.trans('tryhackx-topic-rating.admin.settings.allow_unactivated_label', {}, true),
        help: app.translator.trans('tryhackx-topic-rating.admin.settings.allow_unactivated_help', {}, true),
        type: 'boolean',
    }));

if (tagsOn) {
    adminExtender
        .setting(() => ({
            setting: 'tryhackx-topic-rating.tag_config',
            label: app.translator.trans('tryhackx-topic-rating.admin.settings.tag_config_label', {}, true),
            help: app.translator.trans('tryhackx-topic-rating.admin.settings.tag_config_help', {}, true),
            type: 'tryhackx-topic-rating.tag-permission-tree',
        }))
        .setting(() => ({
            setting: 'tryhackx-topic-rating.display_when_restricted',
            label: app.translator.trans('tryhackx-topic-rating.admin.settings.display_when_restricted_label', {}, true),
            help: app.translator.trans('tryhackx-topic-rating.admin.settings.display_when_restricted_help', {}, true),
            type: 'select',
            options: {
                readonly: app.translator.trans('tryhackx-topic-rating.admin.settings.display_when_restricted_readonly', {}, true),
                hidden: app.translator.trans('tryhackx-topic-rating.admin.settings.display_when_restricted_hidden', {}, true),
                message: app.translator.trans('tryhackx-topic-rating.admin.settings.display_when_restricted_message', {}, true),
            },
        }))
        .setting(() => ({
            setting: 'tryhackx-topic-rating.bypass_groups',
            label: app.translator.trans('tryhackx-topic-rating.admin.settings.bypass_groups_label', {}, true),
            help: app.translator.trans('tryhackx-topic-rating.admin.settings.bypass_groups_help', {}, true),
            type: 'tryhackx-topic-rating.bypass-group-picker',
        }));
}

adminExtender
    .permission(() => ({
        icon: 'fas fa-star',
        label: app.translator.trans('tryhackx-topic-rating.admin.permissions.rate_label', {}, true),
        permission: 'discussion.rate',
    }), 'reply', 65)
    .permission(() => ({
        icon: 'fas fa-star-half-alt',
        label: app.translator.trans('tryhackx-topic-rating.admin.permissions.toggle_label', {}, true),
        permission: 'discussion.rate.toggle',
    }), 'moderate', 65)
    .permission(() => ({
        icon: 'fas fa-eraser',
        label: app.translator.trans('tryhackx-topic-rating.admin.permissions.reset_label', {}, true),
        permission: 'discussion.rate.reset',
    }), 'moderate', 64);

export default [adminExtender];
