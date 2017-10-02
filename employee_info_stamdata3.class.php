<?Php
//A class to extract information about employees from a file exported from agresso business world in the format "stamdata3"
class employee_info_stamdata3
{
	public $xml=false;
	public $error;
	public $debug=false;
	function __construct()
	{
		$this->xml=simplexml_load_file(__DIR__.'/Stamdata3.xml');
		if($this->xml===false)
			throw new exeption('Unable to load file');
	}
	//Perform a root level xpath query
	function query($xpath)
	{
		$result=$this->xml->xpath($xpath);
		if(empty($result))
			return false;
		return $result[0];
	}
	//Find an employee by ResourceId
	//Accepts: ResourceId string
	//Returns: Resource object
	function find_employee($ResourceId)
	{
		$xpath=sprintf('//Resources/Resource/ResourceId[.="%s"]/parent::Resource',$ResourceId);
		$result=$this->xml->xpath($xpath);
		if(empty($result))
		{
			$this->error=sprintf('Employee %s not found',$ResourceId);
			return false;
		}
		return $result[0];
	}

	//Find an employee by SocialSecurityNumber
	//Accepts: ResourceId string
	//Returns: Resource object
	function find_employee_SSN($SocialSecurityNumber)
	{
		$xpath=sprintf('//Resources/Resource/SocialSecurityNumber[.="%s"]/parent::Resource',$SocialSecurityNumber);
		$result=$this->xml->xpath($xpath);
		if(empty($result))
		{
			$this->error=sprintf('Social Security Number %s not found',$SocialSecurityNumber);
			return false;
		}
		return $result[0];
	}
	//Find an employee by name
	//Accepts: First name and last name string
	//Returns: Resource object
	function find_employee_name($FirstName,$Surname)
	{
		$xpath=sprintf('//Resources/Resource/Name[.="%s, %s"]/parent::Resource',$Surname,$FirstName);
		$result=$this->xml->xpath($xpath);
		if(empty($result))
		{
			$this->error=sprintf('Could not find any employees named "%s, %s"',$Surname,$FirstName);
			return false;
		}
		return $result[0];
	}
	//Get ResourceId from a Resource object
	//Accepts: Resource object
	//Returns: ResourceId string
	function ResourceId($Resource)
	{
		if(!is_object($Resource) || $Resource->getName()!='Resource')
			throw new Exception('Wrong object type or not an object');
		return (string)$Resource->ResourceId;
	}
	/*
	Get a relation from an Employment object
	Accepts: Employment Object
	Returns: Relation value string
	*/
	function relation_value($Relation,$relation_name)
	{
		$this->verify_object_type($Relation,'Relations');
		$xpath=sprintf('.//Relation[@Name="%s"]/Value',$relation_name);
		return (string)$Relation->xpath($xpath)[0];
	}

	/*Find an employees main position
	Accepts: ResourceId string or Resource object
	Returns: Employment object*/
	function Main_Position($ResourceId)
	{
		if(is_object($ResourceId))
			$ResourceId=$this->ResourceId($ResourceId);
		$xpath='.//Employment/MainPosition[.="true"]/parent::Employment';///Relations
		$employee=$this->find_employee($ResourceId);
		if($employee===false)
			return false;
		$MainPosition=$employee->xpath($xpath);
		if(empty($MainPosition))
		{
			$this->error=sprintf('%s has no main position',$ResourceId);
			return false;
		}
		return $MainPosition[0];
	}
	/*Get the organization tree for an employee
	Accepts: ResourceId string
	Returns:*/
	function organizational_unit($ResourceId)
	{
		if(!is_string($ResourceId))
			throw new Exception('Argument must be string, provided type is '.gettype($ResourceId));
		$MainPosition=$this->Main_Position($ResourceId);
		if($MainPosition===false)
			return false;
		$OrganizationalUnit=$MainPosition->Relations->xpath('Relation[@ElementType="ORGANIZATIONAL_UNIT"]');
		if(empty($OrganizationalUnit))
		{
			$this->error=sprintf('Main position for %s has no organizational unit',$ResourceId);
			return false;
		}
		return $OrganizationalUnit[0];		
	}
	function organisation_info($Organisation)
	{
		if(empty($Organisation))
			throw new Exception('organisation_info was called with empty argument');
		if(is_object($Organisation) && $Organisation->getName()=='Relation')
			$Organisation=$Organisation->Value;
		$xpath=sprintf('//Organisations/Organisation/Id[.="%s"]/parent::Organisation',$Organisation);
		$result=$this->query($xpath);
		if($result==false)
		{
			$this->error=sprintf('Could not find organisation "%s"',$Organisation);
			return false;
		}
		return $result;
	}
	//Get the manager for an employee
	//Accepts: ResourceId string
	//Returns: Resource object
	function manager($ResourceId)
	{
		/*if(is_string($ResourceId))
			$Resource=$this->find_employee($ResourceId);
		elseif(is_object($Resource) && $Resource->getName()=='Resource')
			$employee=$Resource;
		else
			throw new Exception('Invalid argument');*/

		$organizational_unit=$this->organizational_unit($ResourceId);
		$manager=$this->organisation_info($organizational_unit)->Managers[0]->string;
		return $this->find_employee($manager);
	}

	//Get all employees in one of the following relations: COST_CENTER, WORKPLACE or ORGANIZATIONAL_UNIT
	function get_employees($value,$type='ORGANIZATIONAL_UNIT')
	{
		$xpath=sprintf('//Resources/Resource/Employments/Employment/Relations/Relation[@ElementType="%s"]/Value[.="%s"]/parent::Relation/parent::Relations/parent::Employment/parent::Employments/parent::Resource',$type,$value);
		return $this->xml->xpath($xpath);
	}
	//Accepts: ResourceId string or Resource object
	function organisation_tree($Resource)
	{
		if(is_string($Resource) && strlen($Resource)==5)
			$Resource=$this->organizational_unit($Resource);
		if($Resource===false)
			return false;

		$Organisation_levels=array();
		$Organisation=$this->organisation_info($Resource);
		while(!empty($Organisation->ParentId))
		{
			$Organisation_levels[]=$Organisation;
			//echo sprintf("Parent for %s is %s\n",$Organisation->Name,$Organisation->ParentId);
			$Organisation=$this->organisation_info($Organisation->ParentId);
		}
		if($Organisation===false)
			return false;
		$Organisation_levels[]=$Organisation;
		return $Organisation_levels;
	}
	function organisation_path($EmployeeId)
	{
		$organisation_tree=$this->organisation_tree($EmployeeId);
		if($organisation_tree===false)
			return false;
		$orgstring='';
		foreach(array_reverse($organisation_tree) as $organisation)
		{
			$orgstring.=$organisation->Name."\\";
		}
		$orgstring=substr($orgstring,0,-1);
		return $orgstring;
	}
	//Display relations nicely formatted
	//Accepts: ResourceId string or Resource object
	//Returns: String with relations
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

		print_r($relations);
		$output='';
		foreach($relations->Relations->Relation as $relation)
		{
			$output.=sprintf("Name: %-20s\tValue:\t%-6s Description: %s\n",$relation->attributes()['Name'],$relation->Value,$relation->Description);
		}
		return $output;
	}
	function show_all_relations($org)
	{
		foreach($this->get_employees($org) as $employee)
		{
			//print_r($employee->Employments);
			//print_r($employee);
			echo sprintf("%s: %s\n",$employee->ResourceId,$employee->Name);
			$type='';	$xpath=sprintf('.//Employments/Employment/Relations/Relation[@ElementType="ORGANIZATIONAL_UNIT"]/Value[.="%s"]/parent::Relation/parent::Relations',$org);
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
	//Verify that the
	function verify_object_type($object,$type)
	{
		if(is_object($object))
		{
			if($object->getName()==$type)
				return true;
			else
				throw new Exception('Invalid object type: '.$object->getName());
		}
		else
			throw new Exception(sprintf('Argument must be a %s object, provided type is %s',$type,gettype($object)));
	}
}
