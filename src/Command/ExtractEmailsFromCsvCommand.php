<?php

namespace App\Command;

use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\Writer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ExtractEmailsFromCsvCommand extends ContainerAwareCommand
{
    protected const WRONG_EMAILS_CSV_FILENAME = 'wrong_emails.csv';
    protected const PROPER_EMAILS_CSV_FILENAME = 'proper_emails.csv';
    protected const VALIDATION_SUMMARY_CSV_FILENAME = 'validaion_summary.csv';

    protected const DATA_DIRECTORY_PATH = '/src/Data/';
    protected const RESULT_DIRECTORY_PATH = '/src/Data/Result/';

    protected static $defaultName = 'app:extract-emails-from-csv';

    /**
     * @var string
     */
    protected $dataDir;

    /**
     * @var string
     */
    protected $resultDir;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * ExtractEmailsFromCsvCommand constructor.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->resultDir = $container->get('kernel')->getProjectDir() . self::RESULT_DIRECTORY_PATH;
        $this->dataDir = $container->get('kernel')->getProjectDir() . self::DATA_DIRECTORY_PATH;
        $this->validator = $container->get('validator');
        return parent::__construct();
    }


    protected function configure()
    {
        $this
            ->setDescription('Extracts email addresses from given csv file and stores proper ones in proper_emails.csv file
        and wrong ones in wrong_emails.csv file. Both files are located in {projectRoot}/src/Data/Result folder')
            ->addArgument('filename', InputArgument::REQUIRED, 'Name of csv file with email addresses to be processed')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->hasArgument('filename')) {
            $io->error('You have to pass name of source csv file');
            return;
        }

        $inputFilename = $input->getArgument('filename');
        if (!$inputFilename) {
            $io->error('You have to pass name of source csv file');
        }

        $inputPath = $this->dataDir . $inputFilename;
        if (!is_file($inputPath)) {
            $io->error(sprintf('You have to pass name of proper csv file, something is wrong with %s', $inputFilename));
            exit;
        }
        $inputCsv = Reader::createFromPath($this->dataDir . $inputFilename, 'r');

        $stmt = new Statement();
        $records = $stmt->process($inputCsv);

        $properEmails = [];
        $wrongEmails = [];

        foreach ($records as $email) {
            $valid = $this->isEmailValid($email[0], $this->validator);
            if(($valid)){
                $properEmails[] = [$email[0]];
            }else{
                $wrongEmails[] = [$email[0]];
            }
        }

        $this->putResultsIntoCsv($properEmails, self::PROPER_EMAILS_CSV_FILENAME);
        $this->putResultsIntoCsv($wrongEmails, self::WRONG_EMAILS_CSV_FILENAME);

        $summaryHeaders = ['Proper emails number', 'Wrong emails number'];

        list($wrongEmailsCount, $properEmailsCount, $summaryData) =
            $this->prepareSummaryData($wrongEmails, $properEmails);

        $summaryCsvFilePath = $this->resultDir . self::VALIDATION_SUMMARY_CSV_FILENAME;
        $this->createFile($summaryCsvFilePath);
        $csvWithSummary = Writer::createFromPath($summaryCsvFilePath, 'w');

        try {
            $csvWithSummary->setDelimiter(';');
            $csvWithSummary->insertOne(array_values($summaryHeaders));
            $csvWithSummary->insertOne(array_values($summaryData));
        }catch (Exception $exception) {
            $io->error($exception->getMessage());
            return;
        }

        $io->success(
            sprintf(
                'File %s was successfully processed. There were %s proper emails and %s wrong emails',
                $inputFilename,
                $properEmailsCount,
                $wrongEmailsCount
            ));
    }


    /**
     * @param string $email
     * @param ValidatorInterface $validator
     * @return bool
     */
    protected function isEmailValid($email, ValidatorInterface $validator): bool
    {
        $emailConstraint = new Assert\Email();
        // use the validator to validate the value
        $errors = $validator->validate(
            $email,
            $emailConstraint
        );

        if (0 === count($errors)) {
            return true;
        }
        return false;
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
     * @param array $data
     * @param string $filename
     */
    protected function putResultsIntoCsv($data, $filename): void
    {
        $filePath = $this->resultDir . $filename;
        $this->createFile($filePath);
        $csvWithProperEmails = Writer::createFromPath($filePath, 'w');
        $csvWithProperEmails->insertAll($data);
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
        return array($wrongEmailsCount, $properEmailsCount, $summaryData);
    }
}
