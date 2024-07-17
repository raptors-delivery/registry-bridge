import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';

export default class DevelopersPaymentsIndexController extends Controller {
    @tracked hasStripeConnectAccount = true;
    @tracked table;
    @tracked page = 1;
    @tracked limit = 30;
    @tracked sort = '-created_at';
    @tracked query = null;
    queryParams = ['page', 'limit', 'sort', 'query'];
    columns = [
        {
            label: 'Extension',
            valuePath: 'extension.name',
            width: '20%',
        },
        {
            label: 'Category',
            valuePath: 'extension.category_name',
            width: '20%',
        },
        {
            label: 'Customer',
            valuePath: 'company.name',
            width: '20%',
        },
        {
            label: 'Amount',
            valuePath: 'locked_price',
            width: '20%',
        },
        {
            label: 'Date',
            valuePath: 'created_at',
            width: '20%',
        },
    ];
}
