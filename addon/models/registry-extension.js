import Model, { attr, belongsTo } from '@ember-data/model';

export default class RegistryExtensionModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') company_uuid;
    @attr('string') created_by_uuid;
    @attr('string') registry_user_uuid;
    @attr('string') icon_uuid;
    @attr('string') public_id;

    /** @relationships */
    @belongsTo('company') company;
    @belongsTo('user') user;
    @belongsTo('file') icon;

    /** @attributes */
    @attr('string', { defaultValue: 'https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/default-extension-icon.svg' }) icon_url;
    @attr('string') name;
    @attr('string') subtitle;
    @attr('boolean') payment_required;
    @attr('number') price;
    @attr('number') sale_price;
    @attr('boolean') on_sale;
    @attr('boolean') subscription_required;
    @attr('string') subscription_billing_period;
    @attr('string') subscription_model;
    @attr('number') subscription_amount;
    @attr('object') subscription_tiers;
    @attr('string') slug;
    @attr('string') version;
    @attr('string') fa_icon;
    @attr('string') description;
    @attr('string') promotional_text;
    @attr('string') website_url;
    @attr('string') repo_url;
    @attr('string') support_url;
    @attr('string') privacy_policy_url;
    @attr('string') tos_url;
    @attr('string') copyright;
    @attr('string') primary_language;
    @attr('array') tags;
    @attr('array') languages;
    @attr('object') meta;
    @attr('boolean') core_service;
    @attr('string', { defaultValue: 'pending' }) status;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;
    @attr('date') deleted_at;
}
