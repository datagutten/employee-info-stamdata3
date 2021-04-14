<?php
/**
 * Created by PhpStorm.
 * User: abi
 * Date: 09.08.2019
 * Time: 11:35
 */

use PHPUnit\Framework\TestCase;
use storfollo\EmployeeInfo\employee_info_stamdata3;

class employee_info_stamdata3Test extends TestCase
{
    /**
     * @var employee_info_stamdata3
     */
    public $info;

    public function setUp(): void
    {
        $this->info = new employee_info_stamdata3(__DIR__ . '/test_data/stamdata_test.xml');
    }

    public function testBadFile()
    {
        $this->expectException(RuntimeException::class);
        new employee_info_stamdata3('foo');
    }

    public function testManager()
    {
        $manager = $this->info->manager('53453');
        $this->assertIsObject($manager);
        $this->assertEquals('56584', $manager->{'ResourceId'});
    }

    public function testManagerManager()
    {
        $manager = $this->info->manager('56584');
        $this->assertIsObject($manager);
        $this->assertEquals('58112', (string)$manager->{'ResourceId'});
    }

    public function testOrganisation_path()
    {
        $path = $this->info->organisation_path('53453');
        $this->assertEquals('Ås kommune\Rådmannens ledergruppe\Organisasjon og fellestjeneste\Service og kommunikasjon\IKT', $path);
    }
}
