import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { createRequire } from 'node:module';
import { transformSync } from 'esbuild';
import { BehaviorSubject } from 'rxjs';
import * as math from 'mathjs';

const require = createRequire(import.meta.url);
function loadTypeScript(relativePath, context = {}) {
    const text = readFileSync(new URL(relativePath, import.meta.url), 'utf8');
    const sandbox = { module: { exports: {} }, require, ...context };
    vm.runInNewContext(transformSync(text, { loader: 'ts', format: 'cjs' }).code, sandbox);
    return sandbox.module.exports;
}
const { nsRawCurrency } = loadTypeScript('../../resources/ts/filters/currency.ts', { ns: { currency: { ns_currency_precision: 2 } } });
const Tax = loadTypeScript('../../resources/ts/libraries/tax.ts').default;

// Load the real POS methods without starting the browser application or a server.
const source = readFileSync(new URL('../../resources/ts/pos-init.ts', import.meta.url), 'utf8');
const classSource = source.slice(source.indexOf('export class POS {'), source.indexOf('(window as any).POS = new POS;'));
const compiled = transformSync(classSource, { loader: 'ts', format: 'cjs' }).code;

function fixture(overrides = {}, options = {}) {
    const messages = [];
    const actions = [];
    const requests = [];
    const context = {
        module: { exports: {} }, math, Tax, nsRawCurrency, __: value => value,
        nsSnackBar: { error: message => messages.push(message), info: message => messages.push(message) },
        nsHooks: { doAction: (...args) => actions.push(args), applyFilters: (_name, value) => value },
        nsEvent: { emit() {} },
        nsHttpClient: Object.fromEntries(['post', 'put'].map(method => [method, (url, order) => ({
            subscribe(observer) { requests.push({ method, url, order, observer }); },
        })])),
    };
    vm.runInNewContext(compiled, context);
    const pos = Object.create(context.module.exports.POS.prototype);
    pos._order = new BehaviorSubject({
        total: 100, tendered: 0, change: 0, payments: [],
        customer: { group: { minimal_credit_payment: 20 } }, ...overrides,
    });
    pos._options = new BehaviorSubject({ ns_orders_allow_partial: 'no', ns_pos_complete_sale_audio: '', ns_pos_new_item_audio: '', ...options });
    pos._isSubmitting = false;
    let resets = 0;
    pos.reset = () => { resets++; };
    return { pos, messages, actions, requests, context, get resets() { return resets; } };
}

test('split tender uses numeric addition for amounts returned as strings', () => {
    const { pos } = fixture();
    pos.addPayment({ identifier: 'cash-payment', value: '40' });
    pos.addPayment({ identifier: 'cash-payment', value: '60' });
    assert.equal(pos.order.getValue().tendered, 100);
    assert.equal(pos.order.getValue().change, 0);
    assert.equal(pos.order.getValue().payment_status, 'paid');
});

test('decimal split tender and change do not retain floating point residue', () => {
    const { pos } = fixture({ total: 0.3 });
    pos.addPayment({ value: 0.1 });
    pos.addPayment({ value: 0.2 });
    assert.equal(pos.order.getValue().tendered, 0.3);
    assert.equal(pos.order.getValue().change, 0);
});

test('removing the last tender clears paid status and allows a new tender', () => {
    const { pos } = fixture();
    const payment = { identifier: 'cash-payment', value: 100 };
    pos.addPayment(payment);
    pos.removePayment(payment);
    assert.equal(pos.order.getValue().payment_status, 'unpaid');
    assert.equal(pos.order.getValue().tendered, 0);
    pos.addPayment({ value: 120 });
    assert.equal(pos.order.getValue().change, 20);
    assert.equal(pos.order.getValue().payment_status, 'paid');
});

test('invalid tender is rejected without changing the cart', () => {
    const { pos, messages } = fixture();
    for (const value of [Infinity, NaN, -1, 0, 'bad']) pos.addPayment({ value });
    assert.equal(pos.order.getValue().payments.length, 0);
    assert.equal(messages.length, 5);
});

test('zero-total orders need no payment', () => {
    const { pos } = fixture({ total: 0 });
    pos.computePaid();
    assert.equal(pos.order.getValue().payment_status, 'paid');
});

test('partial tender cannot bypass disabled partial-order setting', { timeout: 1000 }, async () => {
    const { pos, requests } = fixture({ payments: [{ value: 10 }], tendered: 10 });
    await assert.rejects(pos.submitOrder(), error => error.message === 'Partially paid orders are disabled.');
    assert.equal(requests.length, 0);
    assert.equal(pos._isSubmitting, false);
});

test('enabled partial tender enters layaway validation and respects cancellation', { timeout: 1000 }, async () => {
    const { pos, requests } = fixture({ payments: [{ value: 10 }], tendered: 10 }, { ns_orders_allow_partial: 'yes' });
    let validations = 0;
    pos.canProceedAsLaidAway = async () => { validations++; throw { message: 'Canceled' }; };
    await assert.rejects(pos.submitOrder(), error => error.message === 'Canceled');
    assert.equal(validations, 1);
    assert.equal(requests.length, 0);
});

test('approved layaway submits the validated order', { timeout: 1000 }, async () => {
    const { pos, requests } = fixture({ tendered: 20, payments: [{ value: 20 }] }, { ns_orders_allow_partial: 'yes' });
    pos.canProceedAsLaidAway = async order => ({ data: { order: { ...order, instalments: [{ amount: 80 }] } } });
    const pending = pos.submitOrder();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests.length, 1);
    assert.equal(requests[0].order.instalments[0].amount, 80);
    requests[0].observer.next({ message: 'Saved', data: { order: { id: 1 } } });
    await pending;
});

test('hold orders retain their exemption from partial-payment validation', async () => {
    const { pos, requests } = fixture({ payment_status: 'hold' });
    const pending = pos.submitOrder();
    assert.equal(requests.length, 1);
    requests[0].observer.next({ data: { order: { id: 1 } } });
    await pending;
});

test('confirmation sends one request; failure preserves cart and permits retry', async () => {
    const f = fixture({ total: 100, tendered: 100, payments: [{ value: 100 }] });
    const pending = f.pos.submitOrder();
    await assert.rejects(f.pos.submitOrder(), error => /currently being processed/.test(error.message));
    assert.equal(f.requests.length, 1);
    f.requests[0].observer.error({ message: 'Network unavailable' });
    await assert.rejects(pending, error => error.message === 'Network unavailable');
    assert.equal(f.resets, 0);
    assert.equal(f.pos.order.getValue().payments.length, 1);
    const retry = f.pos.submitOrder();
    const saved = { message: 'Saved', data: { order: { id: 1, total: 100, change: 0 } } };
    f.requests[1].observer.next(saved);
    assert.equal(await retry, saved);
    assert.equal(f.resets, 1);
    assert.equal(f.pos._isSubmitting, false);
});

test('insufficient stock without quantity popup settles and releases add-to-cart queue', async () => {
    const { pos, context, messages } = fixture({}, { ns_pos_show_quantity: false });
    const queueSource = readFileSync(new URL('../../resources/ts/pages/dashboard/pos/queues/products/product-quantity.ts', import.meta.url), 'utf8');
    const queueContext = { ...context, module: { exports: {} }, POS: pos,
        require: name => name.endsWith('/lang') ? { __: context.__ }
            : name.endsWith('/bootstrap') ? { nsSnackBar: context.nsSnackBar } : {},
    };
    vm.runInNewContext(transformSync(queueSource, { loader: 'ts', format: 'cjs' }).code, queueContext);
    pos._products = new BehaviorSubject([]);
    const quantities = () => ({ quantity: 0 });
    pos.addToCartQueue = [class { async run() { return { unit_quantity_id: 1, $quantities: quantities }; } }, queueContext.module.exports.ProductQuantityPromise];
    const outcome = await Promise.race([
        pos.addToCart({ id: 1, stock_management: 'enabled', type: 'materialized' }),
        new Promise(resolve => setTimeout(() => resolve('pending'), 100)),
    ]);
    assert.equal(outcome, false);
    assert.equal(pos.processingAddQueue, false);
    assert.equal(pos.products.getValue().length, 0);
    assert.equal(messages.length, 1);

    // A later scan can complete normally after stock becomes available.
    pos._settings = new BehaviorSubject({ ns_pos_items_merge: false });
    pos.recomputeProducts = () => {};
    pos.addToCartQueue[0] = class { async run() { return { unit_quantity_id: 1, $quantities: () => ({ quantity: 2 }) }; } };
    await pos.addToCart({ id: 1, stock_management: 'enabled', type: 'materialized' });
    assert.equal(pos.products.getValue().length, 1);
    assert.equal(pos.products.getValue()[0].quantity, 1);
});

for (const mode of ['normal', 'wholesale', 'custom']) {
    test(`${mode} price preserves quantity, configured discount and exclusive tax`, () => {
        const { pos } = fixture({}, { ns_pos_vat: 'products_vat', ns_pos_prefered_price: 'net_prices' });
        const quantities = { sale_price_net: 100, wholesale_price_net: 80, custom_price_net: 60 };
        const unitPrice = { normal: 100, wholesale: 80, custom: 60 }[mode];
        const product = { product_type: 'product', mode, quantity: 2, discount_type: 'percentage', discount_percentage: 10,
            tax_type: 'exclusive', $quantities: () => quantities, $original: () => ({ tax_group: { taxes: [{ rate: 10 }] } }) };
        pos.computeProduct(product);
        assert.equal(product.unit_price, unitPrice);
        assert.equal(product.discount, unitPrice * 0.2);
        assert.equal(product.total_price, unitPrice * 1.8);
        assert.ok(Math.abs(product.total_tax_value - unitPrice * 0.18) < 1e-9);
    });
}

test('inclusive tax and flat discount remain calculated on the discounted line', () => {
    const { pos } = fixture({}, { ns_pos_vat: 'products_vat', ns_pos_prefered_price: 'gross_prices' });
    const product = { product_type: 'product', mode: 'normal', quantity: 2, discount_type: 'flat', discount: 20,
        tax_type: 'inclusive', $quantities: () => ({ sale_price_gross: 110 }),
        $original: () => ({ tax_group: { taxes: [{ rate: 10 }] } }) };
    pos.computeProduct(product);
    assert.equal(product.total_price, 200);
    assert.ok(Math.abs(product.total_tax_value - 200 / 11) < 1e-9);
});

function loadComponent(filename, f, popup) {
    const text = readFileSync(new URL(`../../resources/ts/popups/${filename}`, import.meta.url), 'utf8');
    const script = text.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
    const sandbox = { ...f.context, module: { exports: {} }, POS: f.pos,
        require: name => name.endsWith('/bootstrap') ? { nsHttpClient: f.context.nsHttpClient, nsSnackBar: f.context.nsSnackBar }
            : name.endsWith('/lang') ? { __: f.context.__ }
            : name.endsWith('/popup') ? { Popup: popup }
            : name.endsWith('/currency') ? { nsRawCurrency } : {},
    };
    vm.runInNewContext(transformSync(script, { loader: 'ts', format: 'cjs' }).code, sandbox);
    return sandbox.module.exports.default;
}

test('product lookup selects a unique match and reports lookup errors', () => {
    const f = fixture();
    const component = loadComponent('ns-pos-search-product.vue', f);
    const selected = [];
    const view = { searchValue: 'FIXTURE', addToCart: product => selected.push(product) };
    component.methods.search.call(view);
    assert.equal(f.requests[0].url, '/api/products/search');
    assert.equal(f.requests[0].order.search, 'FIXTURE');
    const product = { id: 1, name: 'Isolated fixture' };
    f.requests[0].observer.next([product]);
    assert.equal(selected[0], product);
    assert.equal(view.isLoading, false);
    component.methods.search.call(view);
    f.requests[1].observer.error({ message: 'Lookup unavailable' });
    assert.equal(view.isLoading, false);
    assert.equal(f.messages.at(-1), 'Lookup unavailable');
});

test('payment popup sends the saved order to receipt display and keeps failed orders open', async () => {
    const f = fixture();
    let spinnerCloses = 0;
    let paymentCloses = 0;
    const component = loadComponent('ns-pos-payment-popup.vue', f, { show: () => ({ close: () => spinnerCloses++ }) });
    const view = { popup: { close: () => paymentCloses++ } };
    const receipts = [];
    f.pos.printOrderReceipt = (...args) => receipts.push(args);
    f.context.nsSnackBar.success = () => {};
    const saved = { id: 1, total: 100, change: 20 };
    f.pos.submitOrder = async () => ({ message: 'Saved', data: { order: saved } });
    component.methods.submitOrder.call(view);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(receipts[0][0], saved);
    assert.equal(receipts[0][1], 'silent');
    assert.equal(paymentCloses, 1);
    f.pos.submitOrder = async () => { throw { message: 'Save unavailable' }; };
    component.methods.submitOrder.call(view);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(spinnerCloses, 2);
    assert.equal(paymentCloses, 1);
    assert.equal(receipts.length, 1);
    assert.equal(f.messages.at(-1), 'Save unavailable');
});
