<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Shipping;
/**
 * Class Pickup
 *
 * @package Opencart\Catalog\Model\Extension\Opencart\Shipping
 */
class Pickup extends \Opencart\System\Engine\Model {
	/**
	 * @param array<string, mixed> $address
	 *
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		$this->load->language('extension/opencart/shipping/pickup');

		$this->load->model('localisation/geo_zone');

		$results = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('shipping_pickup_geo_zone_id'), (int)$address['country_id'], (int)$address['zone_id']);

		if (!$this->config->get('shipping_pickup_geo_zone_id')) {
			$status = true;
		} elseif ($results) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = [];

		if ($status) {
			$quote_data = [];

			$quote_data['pickup'] = [
				'code'         => 'pickup.pickup',
				'name'         => $this->language->get('text_description'),
				'cost'         => 0.00,
				'tax_class_id' => 0,
				'text'         => $this->currency->format(0.00, $this->session->data['currency'])
			];

			$method_data = [
				'code'       => 'pickup',
				'name'       => $this->language->get('heading_title'),
				'quote'      => $quote_data,
				'sort_order' => $this->config->get('shipping_pickup_sort_order'),
				'error'      => false
			];
		}

		return $method_data;
	}
}
