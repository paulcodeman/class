<?php
class Ozon {
    private $apiToken;
    private $clientId;
    private $allCount = 0;
    const LIMIT = 500;

    const LOGGER = true;

    public function __construct($apiToken, $clientId) {
        $this->apiToken = $apiToken;
        $this->clientId = $clientId;
    }

    private function log($data) {
        if (!self::LOGGER) return;
        echo $data;
    }
    private function sendRequest($url, $data) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Client-Id: ' . $this->clientId,
            'Api-Key: ' . $this->apiToken
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Ошибка cURL: ' . curl_error($ch);
            curl_close($ch);
            exit;
        }

        curl_close($ch);

        $result =json_decode($response, true);

        if (empty($result['result'])) {
            sleep(1);
            $this->log("Повторение запроса: {$url}<br>");
            return $this->sendRequest($url, $data);
        }

        return $result;
    }

    private function getDateRange() {
        $currentDate = new DateTime();
        $dayOfMonth = $currentDate->format('d');

        // Определяем начало периода в зависимости от текущей даты
        if ($dayOfMonth <= 15) {
            $startOfPeriod = $currentDate->format('Y-m-01');
        } else {
            $startOfPeriod = $currentDate->format('Y-m-16');
        }

        $endOfPeriod = $currentDate->format('Y-m-d');
        return [$startOfPeriod, $endOfPeriod];
    }

    public function getMetrika($sku) {
        list($startOfMonth, $currentDate) = $this->getDateRange();

        $data = [
            "date_from" => $startOfMonth,
            "date_to" => $currentDate,
            "metrics" => ["revenue", "ordered_units"],
            "dimension" => ["sku", "day"],
            "filters" => [["key" => "sku", "op" => "EQ", "value" => (string)$sku]],
            "sort" => [["key" => "date_from", "order" => "ASC"]],
            "limit" => 50,
            "offset" => 0
        ];

        $result = $this->sendRequest('https://api-seller.ozon.ru/v1/analytics/data', $data);
        $metrics = [];

        foreach ($result['result']['data'] as $item) {
            $metrics[] = [
                'ср. Цена продажи' => 0,
                'Сумма заказы' => (int)$item['metrics'][0],
                'Заказы' => (int)$item['metrics'][1],
                'Дата' => strtotime($item['dimensions'][1]['id']),
            ];
        }

        usort($metrics, function($a, $b) {
            return $a['Дата'] - $b['Дата'];
        });

        return $metrics;
    }

    public function getStocks() {
        $data = ['filter' => ['visibility' => 'IN_SALE'], 'limit' => self::LIMIT];
        return $this->sendRequest('https://api-seller.ozon.ru/v3/product/info/stocks', $data);
    }

    public function getInfo($productId) {
        $data = ['product_id' => $productId];
        return $this->sendRequest('https://api-seller.ozon.ru/v2/product/info', $data);
    }

    public function getSkuProduct() {
        $skuProduct = [];
        $responseData = $this->getStocks();

        foreach ($responseData['result']['items'] as $item) {
            $productId = $item['product_id'];
            $productDetail = $this->getInfo($productId);
            $sku = $productDetail['result']['sku'];
            $offerId = $productDetail['result']['offer_id'];
            $image = $productDetail['result']['primary_image']??'';
            $present = $productDetail['result']['stocks']['present'];
            $uid = md5($image);

            // Определяем артикул и размер товара
            if (preg_match('~^(.*?)\s*(\d+(:?-\d+)*)\s*$~', $offerId, $match)) {
                $art = trim($match[1]);
                $size = trim($match[2]);
            } else {
                $art = $offerId;
                $size = ' ';
            }

            if (empty($skuProduct[$uid])) {
                $skuProduct[$uid] = [
                    'Блок' => $productDetail['result']['name'],
                    'Фото товара' => $image,
                    'Артикул' => $art,
                    'Предмет' => "https://www.ozon.ru/product/$sku/",
                    'Размер' => [],
                    'Метрика' => $this->getMetrika($sku)
                ];
            }

            if ($size) {
                $skuProduct[$uid]['Размер'][$size] = $present;
            }

            $this->allCount++;
            $this->log('Собираем данные с карточки: '.$skuProduct[$uid]['Предмет'] . "<br>");
        }

        $result = [];
        foreach ($skuProduct as $item) {
            $block = $item['Блок'];
            $result[$block] ??= [];
            $result[$block][] = $item;
        }

        $this->log('Всего: '.$this->allCount);

        return $result;
    }
}
