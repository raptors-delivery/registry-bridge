import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { isArray } from '@ember/array';
import { isBlank } from '@ember/utils';
import { task } from 'ember-concurrency';
import humanize from '@fleetbase/ember-core/utils/humanize';

export default class DevelopersExtensionsEditController extends Controller {
    @service notifications;
    @service intl;
    @tracked isReady = false;

    @task *save() {
        try {
            yield this.model.save();
            const isReady = this.validateExtensionForReview();
            if (isReady === true) {
                this.isReady = isReady;
            }
        } catch (error) {
            this.notifications.warning(error.message);
        }
    }

    @task *startReview() {
        try {
            yield this.model.submitForReview();
            this.notifications.success(this.intl.t('registry-bridge.developers.extensions.extension-form.submission-success-message'));
        } catch (error) {
            if (error && error.message) {
                this.notifications.error(error.messsage);
            }
        }
    }

    @action onIconUploaded(uploadedFile) {
        this.model.setProperties({
            icon_uuid: uploadedFile.id,
            icon_url: uploadedFile.url,
        });

        return this.model.save();
    }

    @action submitForReview() {
        const result = this.validateExtensionForReview();
        if (result === true) {
            // send for review
            return this.startReview.perform();
        }

        if (isArray(result)) {
            this.notifications.warning(result.join('\n'));
        }
    }

    getValidations() {
        const defaultValidationFn = (value) => !isBlank(value);
        return {
            name: (value) => {
                return typeof value === 'string' && value.length > 3;
            },
            description: (value) => {
                return typeof value === 'string' && value.length > 12;
            },
            tags: defaultValidationFn,
            promotional_text: defaultValidationFn,
            subtitle: defaultValidationFn,
            copyright: defaultValidationFn,
            website_url: defaultValidationFn,
            support_url: defaultValidationFn,
            privacy_policy_url: defaultValidationFn,
            icon_uuid: defaultValidationFn,
            latest_bundle_uuid: defaultValidationFn,
            category_uuid: defaultValidationFn,
        };
    }

    validateExtensionForReview() {
        const extension = this.model;
        const validations = this.getValidations();
        const errors = [];
        const hasPackageName = !isBlank(extension.package_name) || !isBlank(extension.composer_name);
        if (!hasPackageName) {
            return ['Extension is missing package name.'];
        }

        Object.keys(validations).forEach((property) => {
            const isValid = validations[property](extension.get(property));
            if (!isValid) {
                errors.push(`${humanize(property)} is invalid.`);
            }
        });

        if (errors.length > 0) {
            return errors;
        }

        return true;
    }
}
