<?php
/**
 * --------------------------------------------------------------------------------
 * Shipping Plugin - UPS
 * --------------------------------------------------------------------------------
 * @package     Joomla 2.5 -  3.x
 * @subpackage  J2 Store
 * @author      Alagesan, J2Store <support@j2store.org>
 * @copyright   Copyright (c) 2016 J2Store . All rights reserved.
 * @license     GNU/GPL license: http://www.gnu.org/licenses/gpl-2.0.html
 * @link        http://j2store.org
 * --------------------------------------------------------------------------------
 *
 * */
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/shipping.php');

class plgJ2StoreShipping_ups extends J2StoreShippingPlugin
{
	/**
	 * @var $_element  string  Should always correspond with the plugin's filename,
	 *                         forcing it to be unique
	 */
	var $_element = 'shipping_ups';
	private $_isLog      = false;
	private $ups_accesskey = '';
	private $ups_userid = '';
	private $ups_password = '';
	private $shipper_number = '';
	private $geozone_id = '';
	private $tax_class_id = '';
	private $endpoint = 'https://www.ups.com/ups.app/xml/Rate';
	private $packaging = array(
		"01" => array(
			"name" 	 => "UPS Letter",
			"length" => "12.5",
			"width"  => "9.5",
			"height" => "0.25",
			"weight" => "0.5"
		),
		"03" => array(
			"name" 	 => "Tube",
			"length" => "38",
			"width"  => "6",
			"height" => "6",
			"weight" => "100" // no limit, but use 100
		),
		"24" => array(
			"name" 	 => "25KG Box",
			"length" => "19.375",
			"width"  => "17.375",
			"height" => "14",
			"weight" => "55.1156"
		),
		"25" => array(
			"name" 	 => "10KG Box",
			"length" => "16.5",
			"width"  => "13.25",
			"height" => "10.75",
			"weight" => "22.0462"
		),
		"2a" => array(
			"name" 	 => "Small Express Box",
			"length" => "13",
			"width"  => "11",
			"height" => "2",
			"weight" => "100" // no limit, but use 100
		),
		"2b" => array(
			"name" 	 => "Medium Express Box",
			"length" => "15",
			"width"  => "11",
			"height" => "3",
			"weight" => "100" // no limit, but use 100
		),
		"2c" => array(
			"name" 	 => "Large Express Box",
			"length" => "18",
			"width"  => "13",
			"height" => "3",
			"weight" => "30"
		)
	);
	/*private $packaging_select = array(
		"01" => "UPS Letter",
		"03" => "Tube",
		"24" => "25KG Box",
		"25" => "10KG Box",
		"2a" => "Small Express Box",
		"2b" => "Medium Express Box",
		"2c" => "Large Express Box",
	);*/

	private $services = array(
		// Domestic
		"12" => "3 Day Select",
		"03" => "Ground",
		"02" => "2nd Day Air",
		"59" => "2nd Day Air AM",
		"01" => "Next Day Air",
		"13" => "Next Day Air Saver",
		"14" => "Next Day Air Early AM",
		// International
		"11" => "Standard",
		"07" => "Worldwide Express",
		"54" => "Worldwide Express Plus",
		"08" => "Worldwide Expedited Standard",
		"65" => "Worldwide Saver",
	);

	private $translated_service_names = array(

		"01"=>"J2STORE_UPS_NEXT_DAY_AIR",
		"02"=>"J2STORE_UPS_SECOND_DAY_AIR",
		"03"=>"J2STORE_UPS_GROUND",
		"12"=>"J2STORE_UPS_THREE-DAY_SELECT",
		"13"=>"J2STORE_UPS_NEXT_DAY_AIR_SAVER",
		"14"=>"J2STORE_UPS_NEXT_DAY_AIR_EARLY_AM",
		"59"=>"J2STORE_UPS_SECOND_DAY_AIR_AM",
		"07"=>"J2STORE_UPS_WORLDWIDE_EXPRESS",
		"08"=>"J2STORE_UPS_WORLDWIDE_EXPEDITED",
		"11"=>"J2STORE_UPS_STANDARD",
		"54"=>"J2STORE_UPS_WORLDWIDE_EXPRESS_PLUS",
		"65"=>"J2STORE_UPS_SAVER"
	);

	private $pickup_code = array(
		'01' => "Daily Pickup",
		'03' => "Customer Counter",
		'06' => "One Time Pickup",
		'07' => "On Call Air",
		'19' => "Letter Center",
		'20' => "Air Service Center",
	);
	var $pickup = '01';
	var $box_count = 0;
	var $residential = 0;
	var $negotiated = 0;
	var $custom_services = array();
	var $signature = 1;
	var $request_weight = 0;
	var $request_weight_unit = 'LBS';
	function __construct ( $subject, $config )
	{
		parent::__construct ( $subject, $config );

		//initialise variables
		$this->ups_accesskey = trim($this->params->get('ups_accesskey', ''));
		$this->ups_userid = trim($this->params->get('ups_userid', ''));
		$this->ups_password = trim($this->params->get('ups_password', ''));
		$this->shipper_number = trim($this->params->get('shipper_number', ''));

		$this->geozone_id = trim($this->params->get('ups_geozone', '0'));
		$this->tax_class_id = trim($this->params->get('ups_tax_class_id', ''));

		$this->pickup = $this->params->get('pickup_type', '');
		$this->signature = $this->params->get('delivery_confirm_type', '');
		$this->residential = $this->params->get('residential',0);
		$this->negotiated = $this->params->get('negotiated',0);
		$this->custom_services  = $this->params->get('ups_services',array());
		//set the log status
		$this->_isLog = $this->params->get ( 'debug' ) ? true : false;
	}

	function setTotalWeight($weight){
		$this->request_weight = $weight;
	}

	function setTotalWeightUnit($weight_unit){
		$this->request_weight_unit = $weight_unit;
	}


	/**
	 * Method to get shipping rates from the USPS
	 *
	 * @param string $element
	 * @param object $order
	 * @return an array of shopping rates
	 */

	function onJ2StoreGetShippingRates($element, $order)
	{
		$rates = array();
		// Check if this is the right plugin
		if (!$this->_isMe($element))
		{
			return $rates;
		}

		//set the address
		$order->setAddress();

		//get the shipping address
		$address = $order->getShippingAddress();

		$geozone_id = $this->params->get('ups_geozone', 0);

		//get the geozones
		$grows = $order->getGeoZone($address['country_id'], $address['zone_id'], $address['postal_code'], $geozone_id);

		if (!$geozone_id) {
			$status = true;
		} elseif ($grows) {
			$status = true;
		} else {
			$status = false;
		}

		if ($status) {
			$rates = $this->getRates($address,$order);

			/// calculate tax
			if(count($rates)) {
				//if the shipping is taxable, calculate it here.
				$tax_class_id = $this->params->get('ups_tax_class_id', '');
				$newRates = array();
				foreach ($rates as $rate) {
					$newRate = array();
					$newRate['name'] = JText::_($rate['name']);
					$newRate['code'] = $rate['code'];
					$newRate['price'] = $rate['price'];
					$newRate['extra'] = $rate['extra'];
					$newRate['tax'] = isset($rate['tax']) ? $rate['tax'] : 0;
					if($tax_class_id) {
						$j2tax = F0FModel::getTmpInstance('TaxProfiles', 'J2StoreModel');
						$taxrates = $j2tax->getTaxwithRates(($newRate['price'] + $newRate['extra']), $tax_class_id);
						if(isset($taxrates->taxtotal)) {
							$shipping_method_tax_total = $taxrates->taxtotal;
							$newRate['tax'] = round($shipping_method_tax_total, 2);
						}
					}
					if(empty($newRate['tax'])) $newRate['tax'] = 0;
					$newRate['total'] =  $newRate['price'] + $newRate['extra'] + $newRate['tax'];
					$newRate['element'] = $rate['element'];
					$newRates[] = $newRate;
				}
				unset($rates);
				$rates = $newRates;
			}

		}

		return $rates;
	}

	private function getRates($address, $order)
	{
		$rates = array();
		//first check if shippable items are in cart
		$products = $order->getItems ();
		if ( $order->isShippingEnabled () === false ) return $rates;

		$weightObject = J2Store::weight();
		$lengthObject = J2Store::length();
		$store_address = J2Store::config();
		$currency = J2Store::currency();

		$countryObject = $this->getCountryById($address['country_id']);
		$items = array();
		$total_weight = 0;
		//1. get all product item
		foreach ($products as $product){
			if (isset($product->cartitem->shipping) && $product->cartitem->shipping) {
				$weight_class_id = isset($product->cartitem->weight_class_id) ? $product->cartitem->weight_class_id : $store_address->config_weight_class_id;
				$length_class_id = isset($product->cartitem->length_class_id) ? $product->cartitem->length_class_id : $store_address->config_length_class_id;
				$weight_unit = $weightObject->getUnit($this->params->get('ups_weight_class_id', 1));
				if(strtoupper($weight_unit) == 'KG') {
					$weight_unit = 'KGS';
				}elseif(strtoupper($weight_unit) == 'LB') {
					$weight_unit = 'LBS';
				}
				$length_unit = $lengthObject->getUnit($this->params->get('ups_length_class_id', 1));
				$c_weight = $weightObject->convert($product->cartitem->weight, $weight_class_id, $this->params->get('ups_weight_class_id', 1));
				$c_weight = ($c_weight < 0.1 ? 0.1 : $c_weight);
				$length = $lengthObject->convert($product->cartitem->length, $length_class_id, $this->params->get('ups_length_class_id', 1));
				$width = $lengthObject->convert($product->cartitem->width, $length_class_id, $this->params->get('ups_length_class_id', 1));
				$height = $lengthObject->convert($product->cartitem->height, $length_class_id, $this->params->get('ups_length_class_id', 1));
				$price = round($product->orderitem_price,2);

				for($i=0;$i<$product->orderitem_quantity;$i++){
					$item = array(
						'weight'=>$c_weight,
						'length' => $length,
						'width' => $width,
						'height' => $height,
						'weight_unit' => strtoupper($weight_unit),
						'length_unit' => strtoupper($length_unit),
						'price' => $price
					);
					$items[] = $item;
					$total_weight += $c_weight;
				}
				$this->setTotalWeightUnit(strtoupper($weight_unit));

			}
		}

		$package_requests = $this->get_package_requests ( $items );

		if ( $package_requests ) {
			$rate_requests = $this->get_rate_requests( $package_requests, $address);
			$this->_log ( json_encode ( $rate_requests ),'REQUEST' );
			foreach ( $rate_requests as $code => $request ) {

				$send_request           = str_replace( array( "\n", "\r" ), '', $request );
				$ups_responses[ $code ] = false;
				$response = $this->sendRequest($send_request);
				$ups_responses[ $code ] = $response;;
			}
			$this->_log ( json_encode ( $ups_responses ),'RESPONSE' );
		}
		$handling = $this->params->get('handling_cost',0);
		$enable_weight_display = $this->params->get('show_weight',0);
		// parse the results
		foreach ( $ups_responses as $code => $xml ) {
			if ( $xml->Response->ResponseStatusCode == 1 ) {

				if ( $this->negotiated && isset( $xml->RatedShipment->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue ) ) {
					$rate_cost = (float) $xml->RatedShipment->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue;
				} else {
					$rate_cost = (float) $xml->RatedShipment->TotalCharges->MonetaryValue;
				}

				$rate_name   = isset($this->translated_service_names[ $code ]) ? $this->translated_service_names[ $code ] : 'UPS Shipping';

				// Signature Adjustment
				switch ( $this->signature ) {
					case 2:
						$rate_cost = $rate_cost + 4;
						break;
					case 3:
						$rate_cost = $rate_cost + 5;
						break;
					default:
						$rate_cost = $rate_cost;
						break;
				}
				if($enable_weight_display){
					$rate_name = JText::_($rate_name).JText::sprintf('J2STORE_WEIGHT_DISPLAY_FORMAT',$this->request_weight,$this->request_weight_unit);;
				}
				$rates[] = array(
					'name' => $rate_name,
					'code' => $code,
					'price'=> $rate_cost,
					'extra'=> (float)$handling,
					'tax'	=> '0',
					'total' =>  $rate_cost,
					'element' => $this->_element
				);
			}
		}
		return $rates;
	}


	private function get_package_requests( $package ) {
		$packing_method = $this->params->get('packing_type','per_item');
		// Choose selected packing
		switch ( $packing_method ) {
			case 'box_packing' :
				$requests = $this->box_shipping( $package );
				break;
			case 'per_item' :
			default :
				$requests = $this->per_item_shipping( $package );
				break;
		}
		return $requests;
	}

	function box_shipping($items){
		$requests = array();
		if ( ! class_exists( 'Packer' ) ) {
			require_once JPATH_SITE."/plugins/j2store/shipping_ups/boxpacker/packer.php";
		}

		$packer = new Packer();
		$ups_packaging = $this->params->get('ups_box',array());
		// Add Standard UPS boxes
		if ( ! empty( $ups_packaging )  ) {
			foreach ( $ups_packaging as $key => $box_code ) {
				$ups_box    = $this->packaging[ $box_code ];
				$newbox = $packer->add_box( $this->get_packaging_dimension( $ups_box['length'] ), $this->get_packaging_dimension( $ups_box['width'] ), $this->get_packaging_dimension( $ups_box['height'] ) );
				$newbox->set_inner_dimensions( $this->get_packaging_dimension( $ups_box['length'] ), $this->get_packaging_dimension( $ups_box['width'] ), $this->get_packaging_dimension( $ups_box['height'] ) );
				$newbox->set_id( $ups_box['name'] );
				if ( $ups_box['weight'] ) {
					$newbox->set_max_weight( $this->get_packaging_weight( $ups_box['weight'] ) );
				}
			}
		}

		$box_list = $this->params->get('box_list',array());

		foreach ($box_list as $key=>$box){
			$newbox = $packer->add_box ( $box->outer_length, $box->outer_width, $box->outer_height, $box->box_weight  );
			if(isset( $box->box_name ) && $box->box_name){
				$box_id = $box->box_name;
			}else{
				$box_id = $key;
			}
			$newbox->set_id($box_id);
			$newbox->set_inner_dimensions( $box->inner_length, $box->inner_width, $box->inner_height );
			if($box->max_weight){
				$newbox->set_max_weight( $box->max_weight );
			}
		}

		foreach ($items as $item){
			$dimensions = array( $item['length'], $item['height'], $item['width']);
			sort ( $dimensions );
			$packer->add_item(
				$dimensions[2],
				$dimensions[1],
				$dimensions[0],
				$item['weight'],
				$item['price']
			);

		}
		$packer->pack ();
		$box_packages = $packer->get_packages ();
		$lengthObject = J2Store::length();
		$length_unit = $lengthObject->getUnit($this->params->get('ups_length_class_id', 1));
		$weightObject = J2Store::weight();
		$weight_unit = $weightObject->getUnit($this->params->get('ups_weight_class_id', 1));
		if(strtoupper($weight_unit) == 'KG') {
			$weight_unit = 'KGS';
		}elseif(strtoupper($weight_unit) == 'LB') {
			$weight_unit = 'LBS';
		}
		$insuredvalue = $this->params->get('send_insuredvalue',1);
		$currency = J2Store::currency();
		$ctr=0;
		$total_weight = 0;
		foreach ( $box_packages as $key => $box_package ) {
			$ctr++;
			$weight     = $box_package->weight;
			$total_weight += $weight;
			$dimensions = array( $box_package->length, $box_package->width, $box_package->height );
			sort( $dimensions );

			$request  = '<Package>' . "\n";
			$request .= '	<PackagingType>' . "\n";
			$request .= '		<Code>02</Code>' . "\n";
			$request .= '		<Description>Package/customer supplied</Description>' . "\n";
			$request .= '	</PackagingType>' . "\n";
			$request .= '	<Description>Rate</Description>' . "\n";
			$request .= '	<Dimensions>' . "\n";
			$request .= '		<UnitOfMeasurement>' . "\n";
			$request .= '	 		<Code>' . strtoupper ( $length_unit ) . '</Code>' . "\n";
			$request .= '		</UnitOfMeasurement>' . "\n";
			$request .= '		<Length>' . round ( $dimensions[2], 2 ) . '</Length>' . "\n";
			$request .= '		<Width>' . round ( $dimensions[1], 2 ) . '</Width>' . "\n";
			$request .= '		<Height>' . round ( $dimensions[0], 2 ) . '</Height>' . "\n";
			$request .= '	</Dimensions>' . "\n";
			$request .= '	<PackageWeight>' . "\n";
			$request .= '		<UnitOfMeasurement>' . "\n";
			$request .= '			<Code>' . $weight_unit . '</Code>' . "\n";
			$request .= '		</UnitOfMeasurement>' . "\n";
			$request .= '		<Weight>' . round ( $weight, 2 ) . '</Weight>' . "\n";
			$request .= '	</PackageWeight>' . "\n";
			// InsuredValue
			if( $insuredvalue ) {
				$request .= '	<PackageServiceOptions>' . "\n";
				$request .= '		<InsuredValue>' . "\n";
				$request .= '			<CurrencyCode>' . $currency->getCode() . '</CurrencyCode>' . "\n";
				$request .= '			<MonetaryValue>' . $box_package->value . '</MonetaryValue>' . "\n";
				$request .= '		</InsuredValue>' . "\n";
				$request .= '	</PackageServiceOptions>' . "\n";
			}
			$request .= '</Package>' . "\n";
			$requests[] = $request;
		}
		$this->setTotalWeight($total_weight);
		return $requests;
	}

	function get_packaging_dimension($dim){
		// 1. cm 2. in , 3. mm
		$to_unit = $this->params->get('ups_length_class_id',2);
		$lengthObject = J2Store::length();
		$dim = $lengthObject->convert($dim, 2, $to_unit);
		return ( $dim < 0 ) ? 0 : $dim;
	}

	function get_packaging_weight($weight){
		// 1.kg 2. g, 3. oz ,4. lb
		$weightObject = J2Store::weight();
		$weight = $weightObject->convert($weight, 4, $this->params->get('ups_weight_class_id', 1));
		return ( $weight < 0 ) ? 0 : $weight;
	}


	function per_item_shipping($items){
		$requests = array();
		$lengthObject = J2Store::length();
		$length_unit = $lengthObject->getUnit($this->params->get('ups_length_class_id', 1));
		$weightObject = J2Store::weight();
		$weight_unit = $weightObject->getUnit($this->params->get('ups_weight_class_id', 1));
		if(strtoupper($weight_unit) == 'KG') {
			$weight_unit = 'KGS';
		}elseif(strtoupper($weight_unit) == 'LB') {
			$weight_unit = 'LBS';
		}
		$insuredvalue = $this->params->get('send_insuredvalue',1);
		$currency = J2Store::currency();
		$ctr=0;
		$weight_total = 0;
		foreach ( $items as $item_id => $item ) {
			$ctr++;
			$dimensions = array( $item['length'], $item['height'], $item['width']);
			sort ( $dimensions );

			$request  = '<Package>' . "\n";
			$request .= '	<PackagingType>' . "\n";
			$request .= '		<Code>02</Code>' . "\n";
			$request .= '		<Description>Package/customer supplied</Description>' . "\n";
			$request .= '	</PackagingType>' . "\n";
			$request .= '	<Description>Rate</Description>' . "\n";
			if ( $dimensions[2] && $dimensions[1] && $dimensions[0] ) {
				$request .= '	<Dimensions>' . "\n";
				$request .= '		<UnitOfMeasurement>' . "\n";
				$request .= '	 		<Code>' . strtoupper ( $length_unit ) . '</Code>' . "\n";
				$request .= '		</UnitOfMeasurement>' . "\n";
				$request .= '		<Length>' . round ( $dimensions[2], 2 ) . '</Length>' . "\n";
				$request .= '		<Width>' . round ( $dimensions[1], 2 ) . '</Width>' . "\n";
				$request .= '		<Height>' . round ( $dimensions[0], 2 ) . '</Height>' . "\n";
				$request .= '	</Dimensions>' . "\n";
			}
			$request .= '	<PackageWeight>' . "\n";
			$request .= '		<UnitOfMeasurement>' . "\n";
			$request .= '			<Code>' . $weight_unit . '</Code>' . "\n";
			$request .= '		</UnitOfMeasurement>' . "\n";
			$request .= '		<Weight>' . round ( $item['weight'], 2 ) . '</Weight>' . "\n";
			$request .= '	</PackageWeight>' . "\n";
			// InsuredValue
			if( $insuredvalue ) {
				$request .= '	<PackageServiceOptions>' . "\n";
				$request .= '		<InsuredValue>' . "\n";
				$request .= '			<CurrencyCode>' . $currency->getCode() . '</CurrencyCode>' . "\n";
				$request .= '			<MonetaryValue>' . $item['price'] . '</MonetaryValue>' . "\n";
				$request .= '		</InsuredValue>' . "\n";
				$request .= '	</PackageServiceOptions>' . "\n";
			}
			$request .= '</Package>' . "\n";
			$requests[] = $request;
			$weight_total += $item['weight'];
		}
		$this->setTotalWeight($weight_total);
		return $requests;
	}


	function get_rate_requests($package_requests, $address){
		$rate_requests = array();
		$store_address = J2Store::config();
		$origin_addressline = '';
		if($store_address ->get('store_address_1')){
			$origin_addressline = $store_address ->get('store_address_1');
		}
		if($store_address ->get('store_address_2')){
			$origin_addressline = " ".$store_address ->get('store_address_2');
		}

		$zone_code = substr($this->getZone($address['zone_id'])->zone_code, 0, 2);
		$origin_zonecode = substr($this->getZone($store_address ->get('zone_id'))->zone_code, 0, 2);

		foreach ( $this->custom_services as $code ) {
			// Security Header
			$request  = "<?xml version=\"1.0\" ?>" . "\n";
			$request .= "<AccessRequest xml:lang='en-US'>" . "\n";
			$request .= "	<AccessLicenseNumber>" . $this->ups_accesskey . "</AccessLicenseNumber>" . "\n";
			$request .= "	<UserId>" . $this->ups_userid . "</UserId>" . "\n";
			// Ampersand will break XML doc, so replace with encoded version.
			$valid_pass = str_replace( '&', '&amp;', $this->ups_password );
			$request .= "	<Password>" . $valid_pass . "</Password>" . "\n";
			$request .= "</AccessRequest>" . "\n";
			$request .= "<?xml version=\"1.0\" ?>" . "\n";
			$request .= "<RatingServiceSelectionRequest>" . "\n";
			$request .= "	<Request>" . "\n";
			$request .= "	<TransactionReference>" . "\n";
			$request .= "		<CustomerContext>Rating and Service</CustomerContext>" . "\n";
			$request .= "		<XpciVersion>1.0</XpciVersion>" . "\n";
			$request .= "	</TransactionReference>" . "\n";
			$request .= "	<RequestAction>Rate</RequestAction>" . "\n";
			$request .= "	<RequestOption>Rate</RequestOption>" . "\n";
			$request .= "	</Request>" . "\n";
			$request .= "	<PickupType>" . "\n";
			$request .= "		<Code>" . $this->pickup . "</Code>" . "\n";
			$request .= "		<Description>" . $this->pickup_code[$this->pickup] . "</Description>" . "\n";
			$request .= "	</PickupType>" . "\n";
			// Shipment information
			$request .= "	<Shipment>" . "\n";
			$request .= "		<Description>J2Store Rate Request</Description>" . "\n";
			$request .= "		<Shipper>" . "\n";
			$request .= "			<ShipperNumber>" . $this->shipper_number . "</ShipperNumber>" . "\n";
			$request .= "			<Address>" . "\n";
			if ( $origin_addressline ) {
				$request .= "			<AddressLine>" . $origin_addressline . "</AddressLine>" . "\n";
			}
			$request .= "				<City>" . $store_address ->get('store_city') . "</City>" . "\n";
			$request .= "				<PostalCode>" . $store_address ->get('store_zip') . "</PostalCode>" . "\n";
			$request .= "				<CountryCode>" . $this->getCountry($store_address ->get('country_id'))->country_isocode_2 . "</CountryCode>" . "\n";
			$request .= "			</Address>" . "\n";
			$request .= "		</Shipper>" . "\n";
			$request .= "		<ShipTo>" . "\n";
			$request .= "			<Address>" . "\n";
			$request .= "				<StateProvinceCode>" . $zone_code . "</StateProvinceCode>" . "\n";
			$request .= "				<PostalCode>" . $address['postal_code'] . "</PostalCode>" . "\n";
			// if Country / State is 'Puerto Rico', set it to be the country, else use set country
			if ( ( "PR" == $zone_code ) && ( "US" == $zone_code ) ) {
				$request .= "			<CountryCode>PR</CountryCode>" . "\n";
			} else {
				$request .= "			<CountryCode>" . $this->getCountry($address['country_id'])->country_isocode_2 . "</CountryCode>" . "\n";
			}
			if ( $this->residential ) {
				$request .= "			<ResidentialAddressIndicator></ResidentialAddressIndicator>" . "\n";
			}
			$request .= "			</Address>" . "\n";
			$request .= "		</ShipTo>" . "\n";
			$request .= "		<ShipFrom>" . "\n";
			$request .= "			<Address>" . "\n";
			if ( $origin_addressline ) {
				$request .= "			<AddressLine>" . $origin_addressline . "</AddressLine>" . "\n";
			}
			$request .= "				<City>" . $store_address ->get('store_city') . "</City>" . "\n";
			$request .= "				<PostalCode>" . $store_address ->get('store_zip') . "</PostalCode>" . "\n";
			$request .= "				<CountryCode>" . $this->getCountry($store_address ->get('country_id'))->country_isocode_2 . "</CountryCode>" . "\n";
			if ( $this->negotiated && $origin_zonecode ) {
				$request .= "			<StateProvinceCode>" . $origin_zonecode . "</StateProvinceCode>" . "\n";
			}
			$request .= "			</Address>" . "\n";
			$request .= "		</ShipFrom>" . "\n";
			$request .= "		<Service>" . "\n";
			$request .= "			<Code>" . $code . "</Code>" . "\n";
			$request .= "		</Service>" . "\n";
			// packages
			foreach ( $package_requests as $key => $package_request ) {
				$request .= $package_request;
			}
			// negotiated rates flag
			if ( $this->negotiated ) {
				$request .= "		<RateInformation>" . "\n";
				$request .= "			<NegotiatedRatesIndicator />" . "\n";
				$request .= "		</RateInformation>" . "\n";
			}
			$request .= "	</Shipment>" . "\n";
			$request .= "</RatingServiceSelectionRequest>" . "\n";
			$rate_requests[$code] = $request;
		}
		return $rate_requests;
	}

	function sendRequest($body){
		// do curl request
		$rsrcCurl = curl_init('https://www.ups.com/ups.app/xml/Rate');
		curl_setopt($rsrcCurl, CURLOPT_HEADER, 0);
		curl_setopt($rsrcCurl, CURLOPT_POST, 1);
		curl_setopt($rsrcCurl, CURLOPT_TIMEOUT, 60);
		curl_setopt($rsrcCurl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($rsrcCurl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($rsrcCurl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($rsrcCurl, CURLOPT_POSTFIELDS, $body);
		$strResult = curl_exec($rsrcCurl);
		$objResult = new SimpleXMLElement($strResult);
		curl_close($rsrcCurl);
		return $objResult;
	}

	/**
	 * Simple logger
	 *
	 * @param string $text
	 * @param string $type
	 * @return void
	 */
	function _log($text, $type = 'message')
	{
		if ($this->_isLog) {
			$file = JPATH_ROOT . "/cache/{$this->_element}.log";
			$date = JFactory::getDate();

			$f = fopen($file, 'a');
			fwrite($f, "\n\n" . $date->format('Y-m-d H:i:s'));
			fwrite($f, "\n" . $type . ': ' . $text);
			fclose($f);
		}
	}
}