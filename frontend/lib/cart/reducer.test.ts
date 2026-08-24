import { describe, expect, it } from 'vitest';
import { MAX_CART_ITEM_QUANTITY, cartReducer, initialCartState } from './reducer';
import type { CartItem } from './types';

const SOAP: Omit<CartItem, 'quantity'> = {
  productId: 1,
  name: 'Savon noir traditionnel',
  slug: 'savon-noir-traditionnel',
  price: '1500.00',
  imageUrl: null,
  sku: 'TC-0001',
};

const OIL: Omit<CartItem, 'quantity'> = {
  productId: 2,
  name: 'Huile de baobab',
  slug: 'huile-de-baobab',
  price: '3000.00',
  imageUrl: 'https://example.com/oil.jpg',
  sku: 'TC-0002',
};

describe('cartReducer', () => {
  it('starts empty', () => {
    expect(initialCartState.items).toEqual([]);
  });

  it('ADD_ITEM adds a new product with the given quantity', () => {
    const state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP, quantity: 2 });

    expect(state.items).toHaveLength(1);
    expect(state.items[0]).toMatchObject({ productId: 1, quantity: 2 });
  });

  it('ADD_ITEM defaults to quantity 1 when omitted', () => {
    const state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP });

    expect(state.items[0].quantity).toBe(1);
  });

  it('ADD_ITEM increases the quantity when the product is already present', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP, quantity: 2 });
    state = cartReducer(state, { type: 'ADD_ITEM', item: SOAP, quantity: 3 });

    expect(state.items).toHaveLength(1);
    expect(state.items[0].quantity).toBe(5);
  });

  it('REMOVE_ITEM removes the matching product only', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP });
    state = cartReducer(state, { type: 'ADD_ITEM', item: OIL });

    state = cartReducer(state, { type: 'REMOVE_ITEM', productId: SOAP.productId });

    expect(state.items).toHaveLength(1);
    expect(state.items[0].productId).toBe(OIL.productId);
  });

  it('UPDATE_QUANTITY sets an explicit quantity', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP });
    state = cartReducer(state, { type: 'UPDATE_QUANTITY', productId: SOAP.productId, quantity: 7 });

    expect(state.items[0].quantity).toBe(7);
  });

  it('UPDATE_QUANTITY never goes below 1, even when asked for 0', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP });
    state = cartReducer(state, { type: 'UPDATE_QUANTITY', productId: SOAP.productId, quantity: 0 });

    expect(state.items).toHaveLength(1);
    expect(state.items[0].quantity).toBe(1);
  });

  it('UPDATE_QUANTITY caps at MAX_CART_ITEM_QUANTITY', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP });
    state = cartReducer(state, { type: 'UPDATE_QUANTITY', productId: SOAP.productId, quantity: 999 });

    expect(state.items[0].quantity).toBe(MAX_CART_ITEM_QUANTITY);
  });

  it('ADD_ITEM caps the combined quantity at MAX_CART_ITEM_QUANTITY too', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP, quantity: 48 });
    state = cartReducer(state, { type: 'ADD_ITEM', item: SOAP, quantity: 5 });

    expect(state.items[0].quantity).toBe(MAX_CART_ITEM_QUANTITY);
  });

  it('INCREMENT increases the quantity by exactly 1', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP, quantity: 4 });
    state = cartReducer(state, { type: 'INCREMENT', productId: SOAP.productId });

    expect(state.items[0].quantity).toBe(5);
  });

  it('DECREMENT decreases the quantity by exactly 1', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP, quantity: 4 });
    state = cartReducer(state, { type: 'DECREMENT', productId: SOAP.productId });

    expect(state.items[0].quantity).toBe(3);
  });

  it('DECREMENT removes the item once quantity reaches 0', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP, quantity: 1 });
    state = cartReducer(state, { type: 'DECREMENT', productId: SOAP.productId });

    expect(state.items).toHaveLength(0);
  });

  it('CLEAR_CART empties the cart regardless of prior content', () => {
    let state = cartReducer(initialCartState, { type: 'ADD_ITEM', item: SOAP });
    state = cartReducer(state, { type: 'ADD_ITEM', item: OIL });
    state = cartReducer(state, { type: 'CLEAR_CART' });

    expect(state.items).toEqual([]);
  });

  it('HYDRATE replaces the whole state with the given one', () => {
    const hydrated = { items: [{ ...SOAP, quantity: 9 }] };
    const state = cartReducer(initialCartState, { type: 'HYDRATE', state: hydrated });

    expect(state).toEqual(hydrated);
  });
});
