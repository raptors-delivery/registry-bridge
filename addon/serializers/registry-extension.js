import ApplicationSerializer from '@fleetbase/ember-core/serializers/application';
import { EmbeddedRecordsMixin } from '@ember-data/serializer/rest';

export default class RegistryExtensionSerializer extends ApplicationSerializer.extend(EmbeddedRecordsMixin) {
    /**
     * Embedded relationship attributes
     *
     * @var {Object}
     */
    get attrs() {
        return {
            company: { embedded: 'always' },
            created_by: { embedded: 'always' },
            category: { embedded: 'always' },
            icon: { embedded: 'always' },
            latest_bundle: { embedded: 'always' },
            screenshots: { embedded: 'always' },
        };
    }
}
