<?php
namespace Opencart\Catalog\Controller\Account;
/**
 * Class Wish List
 *
 * @package Opencart\Catalog\Controller\Account
 */
class WishList extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('account/wishlist');

		if (!$this->customer->isLogged() || (!isset($this->request->get['customer_token']) || !isset($this->session->data['customer_token']) || ($this->request->get['customer_token'] != $this->session->data['customer_token']))) {
			$this->session->data['redirect'] = $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language'));

			$this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language'), true));
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''))
		];

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['list'] = $this->load->controller('account/wishlist.getList');

		$data['continue'] = $this->url->link('account/account', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/wishlist', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('account/wishlist');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	public function getList(): string {
		$data['wishlist'] = $this->url->link('account/wishlist.list', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['cart_add'] = $this->url->link('checkout/cart.add', 'language=' . $this->config->get('config_language'));
		$data['remove'] = $this->url->link('account/wishlist.remove', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));

		$data['products'] = [];

		$this->load->model('account/wishlist');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$results = $this->model_account_wishlist->getWishlist($this->customer->getId());

		foreach ($results as $result) {
			$product_info = $this->model_catalog_product->getProduct($result['product_id']);

			if ($product_info) {
				if ($product_info['image']) {
					$image = $this->model_tool_image->resize(html_entity_decode($product_info['image'], ENT_QUOTES, 'UTF-8'), $this->config->get('config_image_wishlist_width'), $this->config->get('config_image_wishlist_height'));
				} else {
					$image = false;
				}

				if ($product_info['quantity'] <= 0) {
					$this->load->model('localisation/stock_status');

					$stock_status_info = $this->model_localisation_stock_status->getStockStatus($product_info['stock_status_id']);

					if ($stock_status_info) {
						$stock = $stock_status_info['name'];
					} else {
						$stock = '';
					}
				} elseif ($this->config->get('config_stock_display')) {
					$stock = $product_info['quantity'];
				} else {
					$stock = $this->language->get('text_instock');
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if ((float)$product_info['special']) {
					$special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = false;
				}

				$data['products'][] = [
					'product_id' => $product_info['product_id'],
					'thumb'      => $image,
					'name'       => $product_info['name'],
					'model'      => $product_info['model'],
					'stock'      => $stock,
					'price'      => $price,
					'special'    => $special,
					'minimum'    => $product_info['minimum'] > 0 ? $product_info['minimum'] : 1,
					'href'       => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_info['product_id'])
				];
			} else {
				$this->model_account_wishlist->deleteWishlist($this->customer->getId(), $result['product_id']);
			}
		}

		return $this->load->view('account/wishlist_list', $data);
	}

	/**
	 * Add
	 *
	 * @return void
	 */
	public function add(): void {
		$this->load->language('account/wishlist');

		$json = [];

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			$json['error'] = $this->language->get('error_product');
		}

		if (!$json) {
			if (!isset($this->session->data['wishlist'])) {
				$this->session->data['wishlist'] = [];
			}

			$this->session->data['wishlist'][] = $product_id;

			$this->session->data['wishlist'] = array_unique($this->session->data['wishlist']);

			// Logged in. We store the product ID into the wishlist
			if ($this->customer->isLogged()) {
				// Edit the customer's cart
				$this->load->model('account/wishlist');

				$this->model_account_wishlist->addWishlist($this->customer->getId(), $product_id);

				$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product_id), $product_info['name'], $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '')));

				$json['total'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist($this->customer->getId()));
			} else {
				$json['success'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', 'language=' . $this->config->get('config_language')), $this->url->link('account/register', 'language=' . $this->config->get('config_language')), $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . (int)$product_id), $product_info['name'], $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '')));

				$json['total'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Remove
	 *
	 * @return void
	 */
	public function remove(): void {
		$this->load->language('account/wishlist');

		$json = [];

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		if (!$this->customer->isLogged()) {
			$json['error'] = sprintf($this->language->get('error_login'), $this->url->link('account/login', 'language=' . $this->config->get('config_language')), $this->url->link('account/register', 'language=' . $this->config->get('config_language')), $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . (int)$product_id), $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language')));
		}

		if (!$json) {
			$this->load->model('account/wishlist');

			$this->model_account_wishlist->deleteWishlist($this->customer->getId(), $product_id);

			$json['success'] = $this->language->get('text_remove');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
