<?php

class GoogleSheetsCell
{
    private array $format = [];

    public function __construct(
        private GoogleSheets $googleSheets,
        private int $sheetId,
        private int $rowIndex,
        private int $columnIndex
    ) {}

    public function __destruct()
    {
        if ($this->format) {
            $this->googleSheets->updateCellFormat($this->sheetId, $this->rowIndex, $this->columnIndex, $this->format);
        }
    }

    public function __set(string $property, $data): void
    {
        match ($property) {
            'row' => $this->setData($data, false),
            'col' => $this->setData($data, true),
            'image' => $this->setImage($data),
            'link' => $this->setLink($data),
            'formula' => $this->setFormula($data),
            'rotate' => $this->setRotate($data),
            'textAlign' => $this->setTextAlign($data),
            'bold' => $this->setBold($data),
            'border' => $this->setBorders($data, $data, $data, $data),
            'borderTop' => $this->setBorders(0, $data, 0, 0),
            'borderBottom' => $this->setBorders(0, 0, 0, $data),
            'borderLeft' => $this->setBorders($data, 0, 0, 0),
            'borderRight' => $this->setBorders(0, 0, $data, 0),
            'backgroundColor' => $this->setBackgroundColor($data),
            default => null,
        };
    }

    private function pushFormat(array $format): void
    {
        $this->format = array_merge($this->format, $format);
    }

    private function setData($data, $col): void
    {
        if (is_array($data)) {
            if ($col) {
                foreach ($data as $i => $item) {
                    $this->googleSheets->addData($this->sheetId, $this->rowIndex + $i, $this->columnIndex, $item, []+$this->format);
                }
            } else {
                $this->googleSheets->addData($this->sheetId, $this->rowIndex, $this->columnIndex, $data, $this->format);
            }
        } else {
            $this->googleSheets->addData($this->sheetId, $this->rowIndex, $this->columnIndex, $data, $this->format);
        }
    }

    private function setImage(string $url): void
    {
        $this->googleSheets->addFormula($this->sheetId, $this->rowIndex, $this->columnIndex, "=IMAGE(\"$url\")");
    }

    private function setLink(string $url): void
    {
        $this->googleSheets->addFormula($this->sheetId, $this->rowIndex, $this->columnIndex, "=ГИПЕРССЫЛКА(\"$url\")");
    }

    private function setFormula(string $formula): void
    {
        $this->googleSheets->addFormula($this->sheetId, $this->rowIndex, $this->columnIndex, $formula);
    }

    private function setTextAlign(string $align): void
    {
        if ($align === 'center-center') {
            $this->pushFormat([
                'horizontalAlignment' => 'CENTER',
                'verticalAlignment' => 'MIDDLE'
            ]);
        } elseif ($align === 'center') {
            $this->pushFormat([
                'horizontalAlignment' => 'CENTER'
            ]);
        }
    }

    public function setBorders($left = 0, $top = 0, $right = 0, $bottom = 0): void
    {
        $borders = [];

        if ($top > 0) {
            $borders['top'] = [
                'style' => 'SOLID',
                'width' => $top,
                'color' => ['red' => 0, 'green' => 0, 'blue' => 0]
            ];
        }

        if ($bottom > 0) {
            $borders['bottom'] = [
                'style' => 'SOLID',
                'width' => $bottom,
                'color' => ['red' => 0, 'green' => 0, 'blue' => 0]
            ];
        }

        if ($left > 0) {
            $borders['left'] = [
                'style' => 'SOLID',
                'width' => $left,
                'color' => ['red' => 0, 'green' => 0, 'blue' => 0]
            ];
        }

        if ($right > 0) {
            $borders['right'] = [
                'style' => 'SOLID',
                'width' => $right,
                'color' => ['red' => 0, 'green' => 0, 'blue' => 0]
            ];
        }

        if (!empty($borders)) {
            $this->pushFormat(['borders' => $borders]);
        }
    }

    public function setBold(bool $isBold = true): void
    {
        $this->pushFormat([
            'textFormat' => [
                'bold' => $isBold
            ]
        ]);
    }

    private function setBackgroundColor(string $color): void
    {
        $this->pushFormat([
            'backgroundColor' => $this->convertColorToRGB($color)
        ]);
    }

    private function setRotate(int $angle): void
    {
        $this->pushFormat([
            'textRotation' => [
                'angle' => $angle
            ]
        ]);
    }

    private function convertColorToRGB(string $color): array
    {
        $color = ltrim($color, '#');
        return [
            'red' => hexdec(substr($color, 0, 2)) / 255,
            'green' => hexdec(substr($color, 2, 2)) / 255,
            'blue' => hexdec(substr($color, 4, 2)) / 255
        ];
    }
}

class GoogleSheetsID
{
    public function __construct(
        private GoogleSheets $googleSheets,
        private int $sheetId
    ) {}

    public function __set(string $property, $data): void
    {
        if ($property === 'title') {
            $this->rename($data);
        }
    }

    private function rename(string $title): void
    {
        $this->googleSheets->renameSheet($this->sheetId, $title);
    }

    public function cell(int $columnIndex, int $rowIndex): GoogleSheetsCell
    {
        return new GoogleSheetsCell($this->googleSheets, $this->sheetId, $rowIndex, $columnIndex);
    }

    public function merge(int $startColumnIndex = 0, int $startRowIndex = 0, int $width = 1, int $height = 1): GoogleSheetsCell
    {
        $endColumnIndex = $startColumnIndex + $width;
        $endRowIndex = $startRowIndex + $height;
        $this->googleSheets->mergeCells($this->sheetId, $startRowIndex, $endRowIndex, $startColumnIndex, $endColumnIndex);
        return $this->cell($startColumnIndex, $startRowIndex);
    }

    public function sendRequest(): void
    {
        $this->googleSheets->sendRequest();
    }

    public function delete(): void
    {
        if ($this->sheetId) {
            $this->googleSheets->deleteSheet($this->sheetId);
        } else {
            $this->clear();
        }
    }

    public function clear(): void
    {
        $this->googleSheets->clearSheet($this->sheetId);
    }
}

class GoogleSheets
{
    private const BASE_URL = "https://sheets.googleapis.com/v4/spreadsheets/";
    private array $requests = [];

    public function __construct(
        private string $accessToken,
        private string $spreadsheetId
    ) {}

    public function __destruct()
    {
        $this->sendRequest();
    }

    public function __get(string $property): ?GoogleSheetsID
    {
        return $property === 'current' ? new GoogleSheetsID($this, 0) : null;
    }

    private function request(string $method, string $url, ?array $data = null): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ]);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        match ($method) {
            'GET' => curl_setopt($ch, CURLOPT_POST, 0),
            'POST' => curl_setopt($ch, CURLOPT_POST, 1),
            'PATCH' => curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH'),
        };

        $response = curl_exec($ch);
        if ($response === false) {
            die(curl_error($ch));
        }
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            // echo $result['error']['message'] . "<br>";
            // print_r($data);
        }

        return $result;
    }

    public function updateCellFormat(int $sheetId, int $rowIndex, int $columnIndex, array $format): void
    {
        $data = [
            'repeatCell' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'startRowIndex' => $rowIndex,
                    'endRowIndex' => $rowIndex + 1,
                    'startColumnIndex' => $columnIndex,
                    'endColumnIndex' => $columnIndex + 1,
                ],
                'cell' => [
                    'userEnteredFormat' => $format
                ],
                'fields' => 'userEnteredFormat'
            ],
        ];
        $this->batchUpdateRequest($data);
    }

    public function sendRequest(): array
    {
        $url = self::BASE_URL . "{$this->spreadsheetId}:batchUpdate";
        $result = $this->request('POST', $url, [
            'requests' => $this->requests
        ]);
        $this->requests = [];
        return $result;
    }

    private function batchUpdateRequest(array $data): void
    {
        $this->requests[] = $data;
    }

    public function renameSheet(int $sheetId, string $newName): void
    {
        $data = [
            'updateSheetProperties' => [
                'properties' => [
                    'sheetId' => $sheetId,
                    'title' => $newName,
                ],
                'fields' => 'title',
            ],
        ];
        $this->batchUpdateRequest($data);
    }

    public function addSheet(string $title): ?GoogleSheetsID
    {
        $this->sendRequest();
        $data = [
            'addSheet' => [
                'properties' => [
                    'title' => $title,
                ],
            ]
        ];
        $this->batchUpdateRequest($data);
        $result = $this->sendRequest();
        $id = $result['replies'][0]['addSheet']['properties']['sheetId'] ?? null;
        return $id ? new GoogleSheetsID($this, $id) : null;
    }

    public function mergeCells(int $sheetId, int $startRowIndex, int $endRowIndex, int $startColumnIndex, int $endColumnIndex): void
    {
        $data = [
            'mergeCells' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'startRowIndex' => $startRowIndex,
                    'endRowIndex' => $endRowIndex,
                    'startColumnIndex' => $startColumnIndex,
                    'endColumnIndex' => $endColumnIndex,
                ],
                'mergeType' => 'MERGE_ALL',
            ],
        ];
        $this->batchUpdateRequest($data);
    }

    public function addFormula(int $sheetId, int $rowIndex, int $columnIndex, string $formula): void
    {
        $this->addData($sheetId, $rowIndex, $columnIndex, $formula, [],  'formulaValue');
    }

    public function addData(int $sheetId, int $rowIndex, int $columnIndex, $data, array $format = [], string $type = null)    {
        if (is_array($data)) {
            $cellData = [];
            foreach ($data as $item) {
                if ($type) {
                    $valueType = $type;
                } else {
                    $valueType = is_string($item) ? 'stringValue' : (is_int($item) ? 'numberValue' : null);
                }

                $cell['userEnteredValue'] = [
                    $valueType => $item,
                ];

                if ($format) {
                    $cell['userEnteredFormat'] = $format;
                }

                $cellData[] = $cell;
            }
        } else {
            if ($type) {
                $valueType = $type;
            } else {
                $valueType = is_string($data) ? 'stringValue' : (is_int($data) ? 'numberValue' : null);
            }
            $cellData[] = [
                'userEnteredValue' => [
                    $valueType => $data,
                ]
            ];
        }

        if ($valueType === null) {
            throw new InvalidArgumentException('Unsupported data type!');
        }

        $requestData = [
            'updateCells' => [
                'rows' => [
                    [
                        'values' => [$cellData],
                    ],
                ],
                'fields' => 'userEnteredValue,userEnteredFormat',
                'start' => [
                    'sheetId' => $sheetId,
                    'rowIndex' => $rowIndex,
                    'columnIndex' => $columnIndex,
                ],
            ],
        ];

        $this->batchUpdateRequest($requestData);
    }

    public static function getColumnLetters(int $index = null): array|string|null
    {
        // Используем статическую переменную для хранения массива буквенных обозначений колонок
        static $letters = null;

        // Если массив еще не был сгенерирован
        if ($letters === null) {
            $letters = [];

            // Однобуквенные колонки
            for ($i = 0; $i < 26; $i++) {
                $letters[] = chr(65 + $i); // A-Z
            }

            // Двухбуквенные колонки
            for ($i = 0; $i < 26; $i++) {
                for ($j = 0; $j < 26; $j++) {
                    $letters[] = chr(65 + $i) . chr(65 + $j); // AA-ZZ
                }
            }
        }

        // Если задан индекс, возвращаем соответствующий символ или null, если индекс некорректен
        if ($index !== null) {
            return $letters[$index] ?? null;
        }

        // Возвращаем весь массив, если индекс не задан
        return $letters;
    }

    public function deleteSheet(int $sheetId): void
    {
        $data = [
            'deleteSheet' => [
                'sheetId' => $sheetId,
            ],
        ];
        $this->batchUpdateRequest($data);
    }

    public function deleteAllSheets(): void
    {
        // Get all sheets in the document
        $sheets = $this->getSheets();

        // Loop through each sheet and add a delete request
        foreach ($sheets as $sheet) {
            $sheetId = $sheet['properties']['sheetId'];
            if (!$sheetId) {
                $this->clearSheet($sheetId);
                continue;
            }
            $data = [
                'deleteSheet' => [
                    'sheetId' => $sheetId,
                ],
            ];
            $this->batchUpdateRequest($data);
        }
    }

    private function getSheets(): array
    {
        $url = self::BASE_URL . "{$this->spreadsheetId}";
        $response = $this->request('GET', $url);
        return $response['sheets'] ?? [];
    }

    public function clearSheet(int $sheetId = 0): void
    {
        // Clear all data and reset user-entered formats
        $clearData = [
            'updateCells' => [
                'range' => [
                    'sheetId' => $sheetId,
                ],
                'fields' => 'userEnteredValue,userEnteredFormat'
            ]
        ];
        $this->batchUpdateRequest($clearData);

        // Reset dimensions and styles
        $resetProperties = [
            'updateSheetProperties' => [
                'properties' => [
                    'sheetId' => $sheetId,
                    'gridProperties' => [
                        'rowCount' => 1000,
                        'columnCount' => 26,
                        'frozenRowCount' => 0,
                        'frozenColumnCount' => 0
                    ],
                ],
                'fields' => 'gridProperties'
            ]
        ];
        $this->batchUpdateRequest($resetProperties);

        // Unmerge all cells
        $unmergeCells = [
            'unmergeCells' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'startRowIndex' => 0,
                    'endRowIndex' => 1000,
                    'startColumnIndex' => 0,
                    'endColumnIndex' => 26,
                ]
            ]
        ];
        $this->batchUpdateRequest($unmergeCells);
    }
}
