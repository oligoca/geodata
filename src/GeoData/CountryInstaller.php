<?php

namespace Gerardojbaez\Geodata;

use DB;
use Artisan;
use Gerardojbaez\Geodata\Models\City;
use Gerardojbaez\Geodata\Models\Region;
use Gerardojbaez\Geodata\Models\Country;
use Gerardojbaez\Geodata\Exceptions\CountryNotAvailableException;
use Gerardojbaez\Geodata\Exceptions\CountryAlreadyInstalledException;

class CountryInstaller
{
    /**
     * Country name to be installed.
     *
     * @var string
     */
    protected $country;

    /**
     * These are country names that doesn't
     * follow exactly the CamelCase format.
     *
     * @var array
     */
    protected static $namesExceptions = [
        'AntiguaandBarbuda' => 'Antigua and Barbuda',
    ];

    /**
     * Create new CountryInstaller instance.
     *
     * @param  string Country name
     * @throws Gerardojbaez\Geodata\Exceptions\CountryNotAvailableException
     * @return void
     */
    public function __construct($country)
    {
        if (self::valid($country) !== true)
            throw (new CountryNotAvailableException())->setCountry($country);

        $this->country = $country;
    }

    /**
     * Get all available countries.
     *
     * @return array
     */
    public static function getAvailableCountries()
    {
        $seeders = array_diff(scandir(__DIR__.'/Seeders'), array('.', '..'));

        $list = array_map([__CLASS__, "getCleanCountryName"], $seeders);

        return array_combine($list, $list);
    }

    /**
     * Install country.
     *
     * @throws Gerardojbaez\Geodata\Exceptions\CountryAlreadyInstalledException
     * @return void
     */
    public function install()
    {
        $country = $this->country;

        if (self::installed($country))
            throw (new CountryAlreadyInstalledException())->setCountry($country);

        DB::transaction(function() use ($country)
        {
            Artisan::call('db:seed', [
                '--class' => 'Gerardojbaez\Geodata\Seeders\\'.$this->getSeederName($country)
            ]);
        });
    }

    /**
     * Check if country is already installed.
     *
     * @param  string $country
     * @return boolean
     */
    public static function installed($country)
    {
        $seeder = self::loadSeeder($country);
        $country = $seeder->country();

        if (Country::where('code', $country['code'])->count() > 0)
            return true;

        if (Region::where('country_code', $country['code'])->count() > 0)
            return true;

        if (City::where('country_code', $country['code'])->count() > 0)
            return true;

        return false;
    }

    /**
     * Check if country name is valid.
     *
     * @param  string $country
     * @return boolean
     */
    public static function valid($country)
    {
        return file_exists(__DIR__.'/Seeders/'.self::getSeederName($country, true));
    }

    /**
     * Get clean country name from seeder file name.
     *
     * @param  string $str
     * @return string
     */
    protected static function getCleanCountryName($str)
    {
        $str = str_replace('Seeder.php', '', $str);

        if (array_key_exists($str, self::$namesExceptions))
            return self::$namesExceptions[$str];

        // Get words from CamelCase string
        preg_match_all("/((?:^|[A-Z])[a-z]+)/", $str, $words);

        return implode(' ', $words[1]);
    }

    /**
     * Get seeder name from clean country name.
     *
     * @param string   $str
     * @param boolean  $extension  Whether to include php extension or not. Default false.
     * @return string
     */
    protected static function getSeederName($str, $extension = false)
    {
        $namesExceptions = array_flip(self::$namesExceptions);

        if (array_key_exists($str, $namesExceptions))
            $str = $namesExceptions[$str];
        else
            $str = str_replace(' ', '', $str);

        return $str.'Seeder'.($extension ? '.php' : '');
    }

    /**
     * Load country seeder.
     *
     * @param string $country
     * @return mixed Seeder Instance
     */
    protected static function loadSeeder($country)
    {
        $seeder = 'Gerardojbaez\Geodata\Seeders\\'.self::getSeederName($country);
        return new $seeder();
    }
}