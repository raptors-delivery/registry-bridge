import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';

export default class ExtensionMonetizeFormComponent extends Component {
    @tracked subscriptionModelOptions = ['flat_rate', 'tiered', 'usage'];
    @tracked billingPeriodOptions = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
}
