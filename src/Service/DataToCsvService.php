<?php

namespace App\Service;

use App\Service\Interfaces\EmailDataParserInterface;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\Writer;

class DataToCsvService
{
    /**
     * @var string
     */
    protected $delimiter = ';';

    /**
     * @var array
     */
    protected $records = [];

    /**
     * @var string
     */
    protected $sourceFilePath;

    /**
     * @var EmailDataParserInterface
     */
    protected $emailDataParserService;

    /**
     * @var array
     */
    protected $wrongEmails = [];

    /**
     * @var array
     */
    protected $properEmails = [];

    /**
     * CsvService constructor.
     * @param EmailDataParserInterface $emailDataParserService
     */
    public function __construct(
        EmailDataParserInterface $emailDataParserService
    ) {
        $this->emailDataParserService = $emailDataParserService;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validateSettings()
    {
        if(empty($this->sourceFilePath) || !is_string($this->sourceFilePath)) {
            throw new \InvalidArgumentException('Source file path not set or not valid');
        }
    }

    /**
     * @param string $sourceFilePath
     */
    public function setSourceFilePath(string $sourceFilePath): void
    {
        $this->sourceFilePath = $sourceFilePath;
        $this->extractRecords();
    }

    /**
     * @param string $outputFilePath
     * @throws CannotInsertRecord
     */
    public function storeProperEmailAddressesInCsv(string $outputFilePath): void
    {
        $this->validateSettings();

        $records = $this->extractRecords();

        $properEmails = $this->emailDataParserService->getProperEmailAddressees($records);
        $this->properEmails = $properEmails;

        $this->putResultsIntoCsv($properEmails, $outputFilePath);
    }

    /**
     * @param string $outputFilePath
     * @throws CannotInsertRecord
     */
    public function storeWrongEmailAddressesInCsv(string $outputFilePath): void
    {
        $this->validateSettings();

        $records = $this->extractRecords();

        $wrongEmails = $this->emailDataParserService->getWrongEmailAddressees($records);
        $this->wrongEmails = $wrongEmails;

        $this->putResultsIntoCsv($wrongEmails, $outputFilePath);
    }

    /**
     * @param string $outputFilePath
     * @throws \Exception
     */
    public function storeSummaryInCsv(string $outputFilePath)
    {
        $this->validateSettings();

        $this->createFile($outputFilePath);
        $csvWithSummary = Writer::createFromPath($outputFilePath, 'w');

        $summaryHeaders = ['Proper emails number', 'Wrong emails number'];

        if(empty($this->properEmails) || empty($this->wrongEmails)) {
            $this->wrongEmails = $this->emailDataParserService->getWrongEmailAddressees($this->records);
            $this->properEmails = $this->emailDataParserService->getProperEmailAddressees($this->records);
        }

        $summaryData = $this->prepareSummaryData($this->wrongEmails, $this->properEmails);
        try {
            $csvWithSummary->setDelimiter($this->delimiter);
            $this->putResultsIntoCsv([$summaryData], $outputFilePath, array_values($summaryHeaders));
        } catch (Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @param $data
     * @param $filepath
     * @param array $headers
     * @throws \League\Csv\CannotInsertRecord
     */
    protected function putResultsIntoCsv($data, $filepath, $headers = []): void
    {
        $this->createFile($filepath);
        $csv = Writer::createFromPath($filepath, 'w');
        if (!empty($headers)) {
            $csv->insertOne($headers);
        }
        $csv->insertAll($data);
    }

    /**
     * @param string $path
     * @return void
     */
    protected function createFile(string $path): void
    {
        $file = fopen($path, 'w');
        fclose($file);
    }

    /**
     * @return array
     */
    protected function extractRecords(): array
    {
        $this->validateSettings();
        if (empty($this->records)) {
            $inputCsv = Reader::createFromPath($this->sourceFilePath, 'r');
            $records = (new Statement())->process($inputCsv);
            $this->records =  iterator_to_array($records);
        }
        if (empty($this->records)) {
            throw new \InvalidArgumentException('Cannot extract data from given csv file');
        }
        return $this->records;
    }

    /**
     * @param array $wrongEmails
     * @param array $properEmails
     * @return array
     */
    protected function prepareSummaryData($wrongEmails, $properEmails): array
    {
        $wrongEmailsCount = count($wrongEmails);
        $properEmailsCount = count($properEmails);
        $wrongEmailsPartial = $wrongEmailsCount / ($properEmailsCount + $wrongEmailsCount);
        $properEmailsPartial = 1 - $wrongEmailsPartial;

        $wrongEmailsPercentage = sprintf("%.2f%%", $wrongEmailsPartial * 100);
        $properEmailsPercentage = sprintf("%.2f%%", $properEmailsPartial * 100);
        $summaryData = [
            $properEmailsCount . " ($properEmailsPercentage)",
            $wrongEmailsCount . " ($wrongEmailsPercentage)"
        ];
        return $summaryData;
    }

    /**
     * @return array
     */
    public function getWrongEmails(): array
    {
        return $this->wrongEmails;
    }

    /**
     * @return array
     */
    public function getProperEmails(): array
    {
        return $this->properEmails;
    }
}
