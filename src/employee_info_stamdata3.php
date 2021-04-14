<?Php
//A class to extract information about employees from a file exported from agresso business world in the format "stamdata3"
namespace storfollo\EmployeeInfo;

use Exception;
use InvalidArgumentException;
use SimpleXMLElement;

class employee_info_stamdata3
{
	public $xml=false;
	public $error;
	public $debug=false;

    /**
     * employee_info_stamdata3 constructor.
     * @param null $file
     * @throws Exception
     */
	function __construct($file=null)
	{
	    if(empty($file))
	        $file = __DIR__.'/Stamdata3.xml';
		$this->xml=simplexml_load_file($file);
		if($this->xml===false)
			throw new Exception('Unable to load file');
	}

    /**
     * Perform a root level xpath query
     * @param $xpath
     * @return SimpleXMLElement
     * @throws exceptions\NoHitsException
     */
	function query($xpath)
	{
		$result=$this->xml->xpath($xpath);
		if(empty($result))
			throw new exceptions\NoHitsException($xpath);
		return $result[0];
	}
    /**
     * Find an employee by ResourceId
     *
     * @param string $ResourceId
     * @return SimpleXMLElement Resource
     * @throws exceptions\EmployeeNotFoundException
     */
    function find_employee($ResourceId)
	{
		$xpath=sprintf('//Resources/Resource/ResourceId[.="%s"]/parent::Resource',$ResourceId);
		$result=$this->xml->xpath($xpath);
        if(empty($result))
            throw new exceptions\EmployeeNotFoundException(
                sprintf('Employee %s not found',$ResourceId));
        return $result[0];
	}

    /**
     * Find an employee by SocialSecurityNumber
     * @param string $SocialSecurityNumber Social security number
     * @return SimpleXMLElement
     * @throws exceptions\NoHitsException
     */
	function find_employee_SSN($SocialSecurityNumber)
	{
		$xpath=sprintf('//Resources/Resource/SocialSecurityNumber[.="%s"]/parent::Resource',$SocialSecurityNumber);
		$result=$this->xml->xpath($xpath);
		if(empty($result))
		{
			throw new exceptions\NoHitsException('Social Security Number %s not found',$SocialSecurityNumber);
		}
		return $result[0];
	}

    /**
     * Find an employee by name
     * @param string $FirstName First name
     * @param string $Surname Last name
     * @return SimpleXMLElement
     * @throws exceptions\NoHitsException
     */
	function find_employee_name($FirstName,$Surname)
	{
		$xpath=sprintf('//Resources/Resource/Name[.="%s, %s"]/parent::Resource',$Surname,$FirstName);
		$result=$this->xml->xpath($xpath);
		if(empty($result))
		{
			throw new exceptions\NoHitsException(sprintf('Could not find any employees named "%s, %s"',$Surname,$FirstName));
		}
		return $result[0];
	}

    /**
     * Get ResourceId from a Resource object
     * @param SimpleXMLElement $Resource Resource
     * @return string ResourceId
     */
	function ResourceId($Resource)
	{
	    $this->check_xml_tag($Resource, 'Resource');
		return (string)$Resource->{'ResourceId'};
	}

    /**
     * Get a relation from an Employment XML object
     * @param SimpleXMLElement $Relation
     * @param string $relation_name
     * @return string Relation value string
     */
	function relation_value($Relation,$relation_name)
	{
		$this->check_xml_tag($Relation,'Relations');
		$xpath=sprintf('.//Relation[@Name="%s"]/Value',$relation_name);
		return (string)$Relation->xpath($xpath)[0];
	}

	/**
     * Find an employees main position
	 * @param string|SimpleXMLElement $ResourceId string or Resource object
	 * @return SimpleXMLElement Employment object
     * @throws exceptions\DataException
     * @throws exceptions\EmployeeNotFoundException
     */
	function Main_Position($ResourceId)
	{
		if(is_object($ResourceId))
			$ResourceId=$this->ResourceId($ResourceId);
		$xpath='.//Employment/MainPosition[.="true"]/parent::Employment';///Relations
		$employee=$this->find_employee($ResourceId);

		$MainPosition=$employee->xpath($xpath);
		if(empty($MainPosition))
			throw new exceptions\DataException(sprintf('%s has no main position',$ResourceId));
		if(count($MainPosition)>1)
        {
            foreach($MainPosition as $Employment)
            {
                $to = strtotime($Employment->{'DateTo'});
                $from = strtotime($Employment->{'DateFrom'});
                $time = time();
                if($from<=$time && $to>=$time)
                    return $Employment;
            }
            throw new exceptions\DataException(sprintf('%s has multiple main positions, but none is valid',$ResourceId));
        }
        else
		    return $MainPosition[0];
	}

    /**
     * Get the organizational unit for an employee
     *
     * @param string $ResourceId Resource ID
     * @return SimpleXMLElement
     * @throws exceptions\DataException
     * @throws exceptions\EmployeeNotFoundException
     */
	function organizational_unit($ResourceId)
	{
		if(!is_string($ResourceId))
			throw new InvalidArgumentException('Argument must be string, provided type is '.gettype($ResourceId));
		$MainPosition=$this->Main_Position($ResourceId);
		$OrganizationalUnit=$MainPosition->{'Relations'}->xpath('Relation[@ElementType="ORGANIZATIONAL_UNIT"]');
		if(empty($OrganizationalUnit))
            throw new exceptions\DataException(sprintf('Main position for %s has no relation of type organizational unit',$ResourceId));
		return $OrganizationalUnit[0];		
	}

    /**
     * Get information about an Organisation
     * @param string|SimpleXMLElement $Organisation Organisation id or Relation object
     * @return SimpleXMLElement
     * @throws exceptions\NoHitsException
     */
	function organisation_info($Organisation)
	{
		if(empty($Organisation))
			throw new InvalidArgumentException('organisation_info was called with empty argument');
		if(is_object($Organisation) && $Organisation->getName()=='Relation')
			$Organisation=$Organisation->{'Value'};
		$xpath=sprintf('//Organisations/Organisation/Id[.="%s"]/parent::Organisation',$Organisation);
		try {
            $result = $this->query($xpath);
        }
        catch (exceptions\NoHitsException $e)
		{
			throw new exceptions\NoHitsException(sprintf('Could not find organisation "%s"',$Organisation), 0, $e);
		}
		return $result;
	}

    /**
     * Get the manager for an employee
     * @param string $ResourceId ResourceId
     * @return SimpleXMLElement Resource
     * @throws exceptions\DataException
     * @throws exceptions\EmployeeNotFoundException
     * @throws exceptions\NoHitsException
     */
	function manager($ResourceId)
	{
		$organizational_unit=$this->organizational_unit($ResourceId);
		$org = $this->organisation_info($organizational_unit);
		$manager=$org->{'Managers'}[0]->string;
		if($manager==$ResourceId)
        {
            $parent_org = $this->organisation_info($org->{'ParentId'});
            $manager=$parent_org->{'Managers'}[0]->string;
        }
		return $this->find_employee($manager);
	}

    /**
     * Get all employees in one of the following relations: COST_CENTER, WORKPLACE or ORGANIZATIONAL_UNIT
     * @param string $value
     * @param string $type
     * @return SimpleXMLElement[]
     */
	function get_employees($value,$type='ORGANIZATIONAL_UNIT')
	{
		$xpath=sprintf('//Resources/Resource/Employments/Employment/Relations/Relation[@ElementType="%s"]/Value[.="%s"]/parent::Relation/parent::Relations/parent::Employment/parent::Employments/parent::Resource',$type,$value);
		return $this->xml->xpath($xpath);
	}

    /**
     * Get the organization tree for an employee
     * @param string|SimpleXMLElement $Resource Resource ID, Resource XML or Organisation XML
     * @param bool $debug Show debug output
     * @return array
     * @throws exceptions\DataException
     * @throws exceptions\EmployeeNotFoundException
     * @throws exceptions\NoHitsException
     */
    function organisation_tree($Resource, $debug=false)
	{
		if(is_string($Resource) && strlen($Resource)==5) {

            $Relation = $this->organizational_unit($Resource);
            $Organisation = $this->organisation_info($Relation->{'Value'});
        }
        elseif($this->check_xml_tag($Resource, 'Organisation')===true)
            $Organisation=$Resource;
        else
        {
            $Organisation_levels=array();
            $Organisation=$this->organisation_info($Resource);
        }

		while(!empty($Organisation->ParentId))
		{
			$Organisation_levels[]=$Organisation;
			if($debug)
			    echo sprintf("Parent for %s is %s\n",$Organisation->{'Name'},$Organisation->{'ParentId'});
			$Organisation=$this->organisation_info($Organisation->ParentId);
		}

		$Organisation_levels[]=$Organisation;
		return $Organisation_levels;
	}

    /**
     * @param $EmployeeId
     * @return string
     * @throws exceptions\DataException
     * @throws exceptions\EmployeeNotFoundException
     * @throws exceptions\NoHitsException
     */
    function organisation_path($EmployeeId)
	{
		$organisation_tree=$this->organisation_tree($EmployeeId);
		$orgstring='';
		foreach(array_reverse($organisation_tree) as $organisation)
		{
			$orgstring.=$organisation->Name."\\";
		}
		$orgstring=substr($orgstring,0,-1);
		return $orgstring;
	}

    /**
     * Display relations nicely formatted
     *
     * @param string|SimpleXMLElement $Resource_or_Relations
     * @return string
     * @throws exceptions\DataException
     * @throws exceptions\EmployeeNotFoundException
     * @throws Exception
     */
    function show_relations($Resource_or_Relations)
	{
		//ResourceId string
		if(is_string($Resource_or_Relations) && strlen($Resource_or_Relations)==5)
			$relations=$this->Main_Position($Resource_or_Relations);
		elseif(is_object($Resource_or_Relations))
		{
			if($Resource_or_Relations->getName()=='Resource')
				$relations=$this->Main_Position($Resource_or_Relations);
			else
				throw new Exception('Invalid object type: '.$Resource_or_Relations->getName());
		}
		else
			throw new Exception('Argument must be object or string, provided type is '.gettype($Resource_or_Relations));

		//print_r($relations);
		$output='';
		foreach($relations->{'Relations'}->{'Relation'} as $relation)
		{
			$output.=sprintf("Name: %-20s\tValue:\t%-6s Description: %s\n",
                $relation->attributes()['Name'],
                $relation->{'Value'},
                $relation->{'Description'}
                );
		}
		return $output;
	}

    /**
     * Show all relations
     * @param $org
     * @throws exceptions\DataException
     * @throws exceptions\EmployeeNotFoundException
     */
    function show_all_relations($org)
	{
		foreach($this->get_employees($org) as $employee)
		{
			//print_r($employee->Employments);
			//print_r($employee);
			echo sprintf("%s: %s\n",$employee->{'ResourceId'},$employee->{'Name'});
			$xpath=sprintf('.//Employments/Employment/Relations/Relation[@ElementType="ORGANIZATIONAL_UNIT"]/Value[.="%s"]/parent::Relation/parent::Relations',$org);
			echo $this->show_relations($employee->xpath($xpath)[0]);
			/*foreach($employee->xpath($xpath)[0] as $relation)
			{
				//print_r($relation);
				echo sprintf("Name: %-20s\tValue:\t%-6s Description: %s\n",$relation->attributes()['Name'],$relation->Value,$relation->Description);
				//break 2;
			}*/
			echo "\n";
		}
	}

    /**
     * Check if the supplied SimpleXMLElement instance matches the supplied tag name
     * @param SimpleXMLElement $object
     * @param $type string XML tag name
     * @return bool
     */
    function check_xml_tag($object, $type)
	{
		if(is_object($object))
		{
			if($object->getName()==$type)
				return true;
			else
				throw new InvalidArgumentException('Invalid object type: '.$object->getName());
		}
		else
			throw new InvalidArgumentException(sprintf('Argument must be a %s object, provided type is %s',$type,gettype($object)));
	}
}
