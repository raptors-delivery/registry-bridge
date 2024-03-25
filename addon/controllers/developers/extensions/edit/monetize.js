import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';

export default class DevelopersExtensionsEditMonetizeController extends Controller {
    @tracked subscriptionModelOptions = ['flat_rate', 'tiered', 'usage'];
    @tracked billingPeriodOptions = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
}
