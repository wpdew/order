<?php

declare(strict_types=1);

namespace Wpdew\Order;

use Exception;

class Order
{
    public function greet(string $name): string
    {
        return 'Hi ' . $name . ' from Order Class';
    }

    public function sendToTelegram(string $tgtoken, string $tgchatid, array $arrTg): array
	{
		$txt = '';
		foreach ($arrTg as $key => $value) {
			$txt .= "<b>{$key}</b> {$value}%0A";
		}

		$url = "https://api.telegram.org/bot{$tgtoken}/sendMessage?chat_id={$tgchatid}&parse_mode=html&text={$txt}";
		$response = file_get_contents($url);

		if ($response === false) {
			return [
				'status' => 'error',
				'message' => 'Failed to send Telegram message'
			];
		}

		$result = json_decode($response);
		if (!isset($result->ok) || $result->ok !== true) {
			return [
				'status' => 'error',
				'message' => $result->description ?? 'Unknown error'
			];
		}

		return [
			'status' => 'success',
			'message' => 'Message sent successfully'
		];
	}

   public function sendEmail(string $email, array $arrTg): array
	{
		$subject = "Заказ товара ";
		$message = "<b>Заказ товара</b><br/><hr/><br/>";
		foreach ($arrTg as $key => $value) {
			$message .= "<b>{$key}</b> {$value}<br/>";
		}
		$message .= "<hr/><br/><b>Дата: </b> " . date("Y-m-d H:i:s") . "<br/>";
		$message .= "Разработка конфигуратора  <a href='https://t.me/WpDews'>@WpDews</a><br/>";

		$headers = "Content-type: text/html; charset=UTF-8\r\n";
		$success = mail($email, $subject, $message, $headers);

		if (!$success) {
			return [
				'status' => 'error',
				'message' => 'Failed to send email'
			];
		}

		return [
			'status' => 'success',
			'message' => 'Email sent successfully'
		];
	}

    public function getCaptcha(string $secretKey, string $responseToken): object
    {
        $url = "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$responseToken}";
        $result = file_get_contents($url);
        if ($result === false) {
            throw new Exception('Captcha verification failed');
        }

        $decoded = json_decode($result);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON from Captcha response');
        }

        return $decoded;
    }

    public function sendToLpCrm(string $token, string $url, array $dataarray): void
    {
        $products_list = array(
			0 => array(
					'product_id' => $dataarray['product_id'],
					'price'      => $dataarray['product_price'],
					'count'      => $dataarray['count'],
				),
			);

        $products = urlencode(serialize($products_list));
        $sender = urlencode(serialize($_SERVER));
        $data = [
            'key' => $token,
            'order_id' => number_format(round(microtime(true) * 10), 0, '.', ''),
            'country' => 'UA',
            'office' => '1',
            'products' => $products,
            'bayer_name' => $dataarray['name'],
            'phone' => $dataarray['phone'],
            'comment' => $dataarray['product_title'] . ' ' . $dataarray['comment'],
            'payment' => $dataarray['payment'],
            'delivery' => $dataarray['delivery'],
            'delivery_adress' => $dataarray['delivery_adress'],
            'sender' => $sender,
            'utm_source' => $_SESSION['utms']['utm_source'] ?? '',
            'utm_medium' => $_SESSION['utms']['utm_medium'] ?? '',
            'utm_term' => $_SESSION['utms']['utm_term'] ?? '',
            'utm_content' => $_SESSION['utms']['utm_content'] ?? '',
            'utm_campaign' => $_SESSION['utms']['utm_campaign'] ?? '',
            'additional_1' => $dataarray['additional_1'] ?? '',
            'additional_2' => $dataarray['additional_2'] ?? '',
            'additional_3' => $dataarray['additional_3'] ?? '',
            'additional_4' => $dataarray['additional_4'] ?? ''
        ];

        $ch = curl_init("{$url}/api/addNewOrder.html");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception('LPCRM request failed: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    public function sendToMagnetstore(string $token, string $tenant, array $data): array
	{
		$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

		$payload = [
			"office" => 1,
			"country_id" => 250,
			"products" => [[
				"id" => (int) $data['product_id'],
				"amount" => (int) $data['count'],
				"price" => (float) $data['product_price']
			]],
			"fio" => $data['name'] ?? '',
			"phone" => $data['phone'] ?? '',
			"comment" => $data['comment'] ?? '',
			"payment" => 1,
			"additional_field_1" => $data['additional_1'] ?? '',
			"additional_field_2" => $data['additional_2'] ?? '',
			"additional_field_3" => $data['additional_3'] ?? '',
			"additional_field_4" => $data['additional_4'] ?? '',
			"utm_source" => $_SESSION['utms']['utm_source'] ?? '',
			"utm_medium" => $_SESSION['utms']['utm_medium'] ?? '',
			"utm_campaign" => $_SESSION['utms']['utm_campaign'] ?? '',
			"utm_term" => $_SESSION['utms']['utm_term'] ?? '',
			"utm_content" => $_SESSION['utms']['utm_content'] ?? '',
			"order_website" => (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://{$_SERVER['HTTP_HOST']}",
			"user_ip" => $ip_address,
		];

		$url = "https://{$tenant}.go.profi-crm.com/open-api/order-store?token={$token}";

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Accept: application/json',
			]
		]);

		$response = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if ($response === false || !empty($error)) {
			return [
				'status' => 'error',
				'message' => 'Magnetstore request failed: ' . $error
			];
		}

		$result = json_decode($response);
		if (!isset($response)) {
			return [
				'status' => 'error',
				'message' => htmlspecialchars($response) ?? 'Unknown error from Magnetstore API'
			];
		}

		return [
			'status' => 'success',
			'message' => 'Order sent successfully to Magnetstore'
		];
	}

	/**
     * Отправка заказа в SalesDrive
     *
     * @param string $token
     * @param string $url
     * @param array $data
     * @throws Exception
     */
    public function sendToSalesDrive(string $token, string $url, array $data): void
    {
        $products = [[
            "id" => $data['product_id'],
            "name" => $data['product_title'],
            "costPerItem" => $data['product_price'],
            "amount" => $data['count'],
            "description" => "",
            "discount" => "",
            "sku" => ""
        ]];

        $payload = [
            "form" => $token,
            "getResultData" => "1",
            "products" => $products,
            "comment" => $data['comment'],
            "fName" => $data['name'],
            "phone" => $data['phone'],
            "con_comment" => $data['comment'],
            "novaposhta" => [],
            "ukrposhta" => [],
            "prodex24source" => $_SESSION['utms']['utm_source'] ?? '',
            "prodex24medium" => $_SESSION['utms']['utm_medium'] ?? '',
            "prodex24campaign" => $_SESSION['utms']['utm_campaign'] ?? '',
            "prodex24content" => $_SESSION['utms']['utm_content'] ?? '',
            "prodex24term" => $_SESSION['utms']['utm_term'] ?? '',
            "prodex24page" => $_SERVER['HTTP_REFERER'] ?? ''
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            throw new Exception('SalesDrive request failed: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    /**
     * Отправка заказа в KeyCRM
     *
     * @param string $token
     * @param string $sourceId
     * @param array $data
     * @throws Exception
     */
    public function sendToKeyCrm(string $token, string $sourceId, array $data): void
    {
        $payload = [
            "source_id" => $sourceId,
            "buyer" => [
                "full_name" => $data['name'],
                "email" => $data['email'],
                "phone" => $data['phone']
            ],
            "shipping" => [
                "shipping_address_city" => $_POST['address_city'] ?? '',
                "shipping_receive_point" => $_POST['address_street'] ?? '',
                "shipping_address_country" => $_POST['address_country'] ?? '',
                "shipping_address_region" => $_POST['address_region'] ?? '',
                "shipping_address_zip" => $_POST['address_zip'] ?? ''
            ],
            "marketing" => [
                "utm_source" => $_SESSION['utms']['utm_source'] ?? '',
                "utm_medium" => $_SESSION['utms']['utm_medium'] ?? '',
                "utm_campaign" => $_SESSION['utms']['utm_campaign'] ?? '',
                "utm_term" => $_SESSION['utms']['utm_term'] ?? '',
                "utm_content" => $_SESSION['utms']['utm_content'] ?? ''
            ],
            "products" => [[
                "price" => $data['product_price'],
                "quantity" => $data['count'],
                "name" => $data['product_title'],
                "picture" => $_POST['product_url'] ?? '',
                "comment" => $data['comment'],
                "properties" => [[
                    "name" => $data['properties_name'] ?? '',
                    "value" => $data['properties_value'] ?? ''
                ]]
            ]]
        ];

        $ch = curl_init("https://openapi.keycrm.app/v1/order");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ]
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            throw new Exception('KeyCRM order request failed: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    /**
     * Создание лида в KeyCRM
     *
     * @param string $token
     * @param string $sourceId
     * @param string $pipelineId
     * @param array $data
     * @throws Exception
     */
    public function sendToKeyCrmLead(string $token, string $sourceId, string $pipelineId, array $data): void
    {
        $payload = [
            "title" => "Нове замовлення",
            "source_id" => $sourceId,
            "pipeline_id" => $pipelineId,
            "contact" => [
                "full_name" => $data['name'],
                "email" => $data['email'],
                "phone" => $data['phone']
            ],
            "utm_source" => $_SESSION['utms']['utm_source'] ?? '',
            "utm_medium" => $_SESSION['utms']['utm_medium'] ?? '',
            "utm_campaign" => $_SESSION['utms']['utm_campaign'] ?? '',
            "utm_term" => $_SESSION['utms']['utm_term'] ?? '',
            "utm_content" => $_SESSION['utms']['utm_content'] ?? '',
            "products" => [[
                "price" => $data['product_price'],
                "quantity" => $data['count'],
                "name" => $data['product_title'],
                "picture" => $_POST['product_url'] ?? ''
            ]]
        ];

        $ch = curl_init("https://openapi.keycrm.app/v1/pipelines/cards");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ]
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            throw new Exception('KeyCRM lead request failed: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    /**
     * Отправка заказа в EbashCRM
     *
     * @param string $token
     * @param string $url
     * @param string $office
     * @param array $data
     * @throws Exception
     */
    public function sendToEbashCrm(string $token, string $url, string $office, array $data): void
    {
        $products = [
            [
                'product_id' => $data['product_id'],
                'price' => $data['product_price'],
                'count' => $data['count'],
            ]
        ];

        $payload = [
            'key' => $token,
            'order_id' => number_format(round(microtime(true) * 10), 0, '.', ''),
            'country' => 'UA',
            'office' => $office,
            'products' => urlencode(serialize($products)),
            'bayer_name' => $data['name'],
            'phone' => $data['phone'],
            'comment' => $data['comment'],
            'payment' => '1',
            'sender' => urlencode(serialize($_SERVER))
        ];

        $ch = curl_init("{$url}order/inc/api.php");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            throw new Exception('EbashCRM request failed: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    /**
     * Отправка заказа в KeepinCRM
     *
     * @param string $token
     * @param array $data
     * @return string JSON response
     * @throws Exception
     */
    public function sendToKeepinCRM(string $token, array $data): string
    {
        $payload = [
            'title' => '#' . rand(100, 999) . ' - Замовлення із сайту',
            'total' => (float)$data['product_price'] * (int)$data['count'],
            'currency' => 'UAH',
            'stage_id' => 6,
            'source_id' => 5,
            'funnel_id' => 1,
            'client_attributes' => [
                'person' => $data['name'],
                'email' => $data['email'],
                'status_id' => 1,
                'lead' => false,
                'phones' => [$data['phone']],
                'custom_fields' => [[
                    'name' => 'Адреса',
                    'value' => $data['delivery_adress']
                ]]
            ],
            'contractor_attributes' => [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => preg_replace('/[^0-9]/', '', $data['phone'])
            ],
            'jobs_attributes' => [[
                'amount' => (int)$data['count'],
                'title' => $data['product_title'],
                'product_attributes' => [
                    'sku' => $data['product_id'],
                    'title' => $data['product_title'],
                    'price' => (float)$data['product_price'],
                    'currency' => 'UAH'
                ]
            ]],
            'custom_fields' => [[
                'name' => 'Тип оплати',
                'value' => $data['payment']
            ], [
                'name' => 'Тип доставки',
                'value' => $data['delivery']
            ]]
        ];

        $ch = curl_init('https://api.keepincrm.com/v1/agreements');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Auth-Token: ' . $token
            ]
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            throw new Exception('KeepinCRM request failed: ' . curl_error($ch));
        }
        curl_close($ch);

        return $res;
    }
	
}
