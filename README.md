<h3>Simple application made to parse emails listed in input file into 3 output files</h3>

Based on Symfony skeleton v4.1. Uses http://csv.thephpleague.com/ for parsing CSV files.

Usage:
+ clone repository
+ run `composer install` in application directory
+ use Symfony built in server `bin/console server:start` in app directory
+ example file is located in `{application_root}/src/Data/data.csv`
+ run `bin/console app:extract-emails-from-csv data.csv` from command line in app directory
+ check out the contents of `{application_root}/src/Data/Result/` folder. There should be 3 files:
    + `proper_emails.csv`
    + `wrong_emails.csv`
    + `validation_summary.csv`
    
That's it.