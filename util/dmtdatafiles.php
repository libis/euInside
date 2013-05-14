
<?php
    class DataFile  
     {  
            private $filePath; 
			private $fileName;
			private $fileType;
			
            # Constructor  
            public function __construct()  
             {  
                    self::__set('filePath', "");  
					self::__set('fileName', "");   
					self::__set('fileType', ""); 

             }  
      
            # Setter  
            public function __set($name, $value)  
             {  
                    switch ($name)  
                     {  
                            case 'filePath':  
                              $this->filePath = $value;  
                            break;
							
                            case 'fileName':  
                              $this->fileName = $value;  
                            break;
							
                            case 'fileType':  
                              $this->fileType = $value;  
                            break;
							
                            default:  
                              throw new Exception("Attempt to set a non-existing property: $name");  
                            break;  
                     }  
             }  
      
            # Getter  
            public function __get($name)  
             {  
                    if (in_array($name, array('filePath')))  
                     return $this->$name;  
					 
                    if (in_array($name, array('fileName')))  
                     return $this->$name; 
					 
                    if (in_array($name, array('fileType')))  
                     return $this->$name; 

					 switch ($name)  
                     {  
                            default:  
                              throw new Exception("Attempt to get a non-existing property: $name");  
                            break;  
                     }  
             }  
      
     }        

?>

