import './random-impact-toggle.scss';
import register from 'ShopUi/app/registry';

export default register(
    'random-impact-toggle',
    () =>
        import(
            /* webpackMode: "lazy" */
            /* webpackChunkName: "random-impact-toggle" */
            './random-impact-toggle'
        ),
);
