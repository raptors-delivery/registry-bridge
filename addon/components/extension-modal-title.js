import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';

export default class ExtensionModalTitleComponent extends Component {
    @tracked extension;
    @tracked detailsContainerClass = 'mb-4';

    constructor(owner, { options, detailsContainerClass }) {
        super(...arguments);
        this.extension = options.extension;
        this.detailsContainerClass = detailsContainerClass ?? 'mb-4';
    }
}
