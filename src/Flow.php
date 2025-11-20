<?php

/*
 * Flow API
 *
 * Version: 1.4
 * Date:    2015-05-25
 * Author:  flow.cl
 */

namespace CokeCancino\LaravelFlow;

use Exception;

class Flow {

    protected $order = array();

    //Método constructor de compatibilidad
    function flowAPI() {;
        $this->__construct();
    }

    //Constructor de la clase
    function __construct() {
        //global $flow_medioPago;
        $this->secretKey = config('flow.secret_key');
        $this->api_key = config('flow.api_key');
    }


    /**
     * Crea una nueva Orden para ser enviada a Flow
     *
     * @param string $orden_compra El número de Orden de Compra del Comercio
     * @param string $monto El monto de Orden de Compra del Comercio
     * @param string $concepto El concepto de Orden de Compra del Comercio
     * @param string $email_pagador El email del pagador de Orden de Compra del Comercio
     * @param mixed $medioPago El Medio de Pago (1,2,9)
     *
     * @return string flow_pack Paquete de datos firmados listos para ser enviados a Flow
     */
    public function createNewOrder($orden_compra, $monto,  $concepto, $email_pagador, $optionals = [], $medioPago = "Non") {
        $this->flow_log("Iniciando nueva Orden", "new_order");
        if(!isset($orden_compra,$monto,$concepto)) {
            $this->flow_log("Error: No se pasaron todos los parámetros obligatorios","new_order");
        }
        if($medioPago == "Non") {
            $medioPago = config('flow.medioPago');
        }
        if(!is_numeric($monto)) {
            $this->flow_log("Error: El parámetro monto de la orden debe ser numérico","new_order");
            throw new Exception("El monto de la orden debe ser numérico");
        }

        $url_confirmacion = $this->generarUrl(config('flow.url_confirmacion'));
        $url_retorno = $this->generarUrl(config('flow.url_retorno'));

        $params = array(
            "apiKey" => $this->api_key,
            "commerceOrder" => $orden_compra,
            "subject" => $concepto,
            "currency" => "CLP",
            "amount" => $monto,
            "email" =>  $email_pagador,
            "paymentMethod" => $medioPago,
            "urlConfirmation" => $url_confirmacion,
            "urlReturn" => $url_retorno,
            "optional" => json_encode($optionals)
        );

        $params["s"] = $this->signParams($params);
        return $params;
    }

		public function createNewClient($name, $email, $userId){
			$params = array(
					"apiKey" => $this->api_key,
					"name" => $name,
					"email" =>  $email,
					"externalId" => $userId
			);

			$params["s"] = $this->signParams($params);
			return $params;
		}

    // Llamadas a apis de Flow
		public function createClient($signedParams) {
				$url = config('flow.base_url') . '/customer/create';
				$response = $this->httpPost($url, $signedParams);
				$data = json_decode($response["output"], true);
				return $data;
		}

		public function creditCardRegister($customerId){
				$url = config('flow.base_url') . '/customer/register';

				$params = array(
						"apiKey" => $this->api_key,
						"customerId" => $customerId,
						"url_return" => $this->generarUrl(config('flow.url_credit_card_return'))
				);

				$params["s"] = $this->signParams($params);
				$response = $this->httpPost($url, $params);        
				$data = json_decode($response["output"], true);
				return $data;
		}

		public function creditCardRegisterStatus($token){
				$url = config('flow.base_url') . '/customer/getRegisterStatus';

				$params = array(
						"apiKey" => $this->api_key,
						"token" => $token
				);

				$params["s"] = $this->signParams($params);
				$response = $this->httpGet($url, $params);        
				$data = json_decode($response["output"], true);
				return $data;
		}

		public function autoChargeClient($customerId, $amount, $subject, $commerceOrder) {
				$url = config('flow.base_url') . '/customer/charge';

				$params = array(
						"apiKey" => $this->api_key,
						"customerId" => $customerId,
						"amount" => $amount,
						"subject" => $subject,
						"commerceOrder" => $commerceOrder
				);

				$params["s"] = $this->signParams($params);
				$response = $this->httpPost($url, $params);
				$data = json_decode($response["output"], true);
				return $data;
		}
		
    public function createFlowOrder($signedParams) {
        $url = config('flow.base_url') . '/payment/create';
        $response = $this->httpPost($url, $signedParams);
        $data = json_decode($response["output"], true);
        return $data;
    }

    public function getOrderStatusByOrderID($flowOrderID){
        $url = config('flow.base_url') . '/payment/getStatusByFlowOrder';

        $params = array(
            "apiKey" => $this->api_key,
            "flowOrder" => $flowOrderID
        );

        $params["s"] = $this->signParams($params);
        $response = $this->httpGet($url, $params);        
        $data = json_decode($response["output"], true);
        return $data;
    }

    public function getOrderStatusByToken($flowOrderToken){
        $url = config('flow.base_url') . '/payment/getStatus';
        
        $params = array(
            "apiKey" => $this->api_key,
            "token" => $flowOrderToken
        );

        $params["s"] = $this->signParams($params);
        $response = $this->httpGet($url, $params);
        $data = json_decode($response["output"], true);
        return $data;
    }

    /**
     * Registra en el Log de Flow
     *
     * @param string $message El mensaje a ser escrito en el log
     * @param string $type Identificador del mensaje
     *
     */
    public function flow_log($message, $type) {
        //global $flow_logPath;
        $file = fopen(config('flow.logPath') . "/flowLog_" . date("Y-m-d") .".txt" , "a+");
        fwrite($file, "[".date("Y-m-d H:i:s.u")." ".getenv('REMOTE_ADDR')." ".getenv('HTTP_X_FORWARDED_FOR')." - $type ] ".$message . PHP_EOL);
        fclose($file);
    }

    /**
     * Funcion que hace el llamado via http POST
     * @param string $url url a invocar
     * @param array $params los datos a enviar
     * @return array el resultado de la llamada
     * @throws Exception
     */
    private function httpPost($url, $params) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $output = curl_exec($ch);
        if($output === false) {
            $error = curl_error($ch);
            throw new Exception($error, 1);
        }
        $info = curl_getinfo($ch);
        curl_close($ch);
        return array("output" =>$output, "info" => $info);
    }
    
    /**
     * Funcion que hace el llamado via http GET
     * @param string $url url a invocar
     * @param array $params los datos a enviar
     * @return array el resultado de la llamada
     * @throws Exception
     */
    private function httpGet($url, $params) {
        $url = $url . "?" . http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($ch);
        if($output === false) {
            $error = curl_error($ch);
            throw new Exception($error, 1);
        }
        $info = curl_getinfo($ch);
        curl_close($ch);
        return array("output" =>$output, "info" => $info);
    }

    /**
     * Genera una URL utilizando las funciones de Laravel
     *
     * @param  mixed  $url
     * @return string
     */
    private function generarUrl($url)
    {
        if (is_array($url)) {
            if (array_key_exists('url', $url))
                return url($url['url']);

            if (array_key_exists('route', $url))
                return route($url['route']);

            if (array_key_exists('action', $url))
                return action($url['action']);

            return '';
        } else {
            return $url;
        }
    }

    /**
     * Funcion que firma los parametros
     * @param string $params Parametros a firmar
     * @return string de firma
     * @throws Exception
     */
    private function signParams($params) {
        $keys = array_keys($params);
        sort($keys);
        $toSign = "";
        foreach ($keys as $key) {
            $toSign .= $key . $params[$key];
        }
        if(!function_exists("hash_hmac")) {
            throw new Exception("function hash_hmac not exist", 1);
        }
        return hash_hmac('sha256', $toSign , $this->secretKey);
    }
}
