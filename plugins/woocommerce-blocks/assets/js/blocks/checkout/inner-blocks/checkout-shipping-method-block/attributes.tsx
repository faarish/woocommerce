/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import formStepAttributes from '../../form-step/attributes';
import { defaultShippingText, defaultLocalPickupText } from './constants';

const defaultTitle = getSetting( 'localPickupEnabled', false )
	? __( 'Delivery', 'woocommerce' )
	: __( 'Shipping method', 'woocommerce' );

export default {
	...formStepAttributes( {
		defaultTitle,
		defaultDescription: __(
			'Select how you would like to receive your order.',
			'woocommerce'
		),
	} ),
	className: {
		type: 'string',
		default: '',
	},
	showIcon: {
		type: 'boolean',
		default: true,
	},
	showPrice: {
		type: 'boolean',
		default: false,
	},
	localPickupText: {
		type: 'string',
		default: defaultLocalPickupText,
	},
	shippingText: {
		type: 'string',
		default: defaultShippingText,
	},
	lock: {
		type: 'object',
		default: {
			move: true,
			remove: true,
		},
	},
};
