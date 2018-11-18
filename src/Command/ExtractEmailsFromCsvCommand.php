<?php

namespace App\Command;

use App\Service\DataToCsvService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ExtractEmailsFromCsvCommand extends ContainerAwareCommand
{
    protected const WRONG_EMAILS_CSV_FILENAME = 'wrong_emails.csv';
    protected const PROPER_EMAILS_CSV_FILENAME = 'proper_emails.csv';
    protected const VALIDATION_SUMMARY_CSV_FILENAME = 'validaion_summary.csv';
    protected const VALIDATION_SUMMARY_TXT_FILENAME = 'validaion_summary.txt';

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
     * @var DataToCsvService
     */
    protected $csvService;

    /**
     * ExtractEmailsFromCsvCommand constructor.
     * @param ContainerInterface $container
     * @param DataToCsvService $csvService
     */
    public function __construct(
        ContainerInterface $container,
        DataToCsvService $csvService
    ) {
        $this->resultDir = $container->get('kernel')->getProjectDir() . self::RESULT_DIRECTORY_PATH;
        $this->dataDir = $container->get('kernel')->getProjectDir() . self::DATA_DIRECTORY_PATH;
        $this->csvService = $csvService;
        return parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Extracts email addresses from given csv file and stores proper ones in proper_emails.csv file
        and wrong ones in wrong_emails.csv file. Both files are located in {projectRoot}/src/Data/Result folder')
            ->addArgument('filename', InputArgument::REQUIRED, 'Name of csv file with email addresses to be processed');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
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

        try {
            $this->csvService->setSourceFilePath($this->dataDir . $inputFilename);
            $this->csvService->storeProperEmailAddressesInCsv($this->resultDir . self::PROPER_EMAILS_CSV_FILENAME);
            $this->csvService->storeWrongEmailAddressesInCsv($this->resultDir . self::WRONG_EMAILS_CSV_FILENAME);
            $this->csvService->storeSummaryInCsv($this->resultDir . self::VALIDATION_SUMMARY_CSV_FILENAME);
        } catch (\Exception $exception) {
            $io->error($exception->getMessage());
            return;
        }

        $properEmailsCount = count($this->csvService->getProperEmails());
        $wrongEmailsCount = count($this->csvService->getWrongEmails());

        $successMessage = sprintf(
            'File %s was successfully processed. There were %s proper emails and %s wrong emails',
            $inputFilename,
            $properEmailsCount,
            $wrongEmailsCount
        );

        $summaryTXTFilePath = $this->resultDir . self::VALIDATION_SUMMARY_TXT_FILENAME;
        file_put_contents($summaryTXTFilePath, $successMessage);

        $io->success($successMessage);
    }
}
