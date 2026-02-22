import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export default [
    new Extend.Admin()
        .setting(() => ({
            setting: 'tryhackx-topic-rating.enabled',
            label: app.translator.trans('tryhackx-topic-rating.admin.settings.enabled_label', {}, true),
            help: app.translator.trans('tryhackx-topic-rating.admin.settings.enabled_help', {}, true),
            type: 'boolean',
        }))
        .setting(() => ({
            setting: 'tryhackx-topic-rating.allow_unactivated',
            label: app.translator.trans('tryhackx-topic-rating.admin.settings.allow_unactivated_label', {}, true),
            help: app.translator.trans('tryhackx-topic-rating.admin.settings.allow_unactivated_help', {}, true),
            type: 'boolean',
        }))
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
        }), 'moderate', 64),
];
