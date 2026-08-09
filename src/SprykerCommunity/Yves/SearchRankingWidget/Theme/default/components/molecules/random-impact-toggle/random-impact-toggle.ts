import Component from 'ShopUi/models/component';

/**
 * Drives every `.random-impact-badge` on the page via ONE global toggle (a `random-impact-visible` class
 * on `<body>`) instead of coordinating with each badge individually — see random-impact-badge.scss's own
 * `body.random-impact-visible .random-impact-badge` rule. Simpler than dispatching a custom event to N
 * badge instances, and correct here specifically because every badge's visibility flips in lockstep with
 * this single checkbox — there's no per-badge state to track.
 */
export default class RandomImpactToggle extends Component {
    checkbox: HTMLInputElement;

    protected readyCallback(): void {
        this.checkbox = <HTMLInputElement>this.querySelector(`.${this.jsName}__checkbox`);

        if (!this.checkbox) {
            return;
        }

        this.checkbox.addEventListener('change', () => this.toggleVisibility());
    }

    protected toggleVisibility(): void {
        document.body.classList.toggle('random-impact-visible', this.checkbox.checked);
    }
}
