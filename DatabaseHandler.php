<?php
class DatabaseHandler
{
    private $conn;

    public function __construct($servername, $username, $password, $database)
    {
        $this->conn = new mysqli($servername, $username, $password, $database);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function insertData($data)
    {   
        // $jsonData = file_get_contents('data.json');
        // Decode JSON data
        // $data = json_decode($jsonData, true);
        print_r($data);
        foreach ($data as  $value) {
            foreach ($value['color_variants'] as  $Colourvalue) {
                foreach ($Colourvalue as  $Colourdata) {
                    $get_data = "SELECT * FROM items where  name='{$value['product_name']}' and lux_color_code='{$Colourdata['color_code']}'";
                    $result = $this->conn->query($get_data);
                    if ($result) {
                        $row = $result->fetch_assoc();
                        if ($row) {
                            $update_data = "UPDATE items
                                SET brand_name='{$value['category_name']}',
                                    box_size='{$value['Size']}',
                                    sc_frame_color='{$Colourdata['color_name']}',
                                    sc_frame_type='{$value['Type']}',
                                    sc_frame_gender ='{$value['Gender']}',
                                    sc_frame_rxable='{$value['Rx-able']}'
                                WHERE name='{$value['product_name']}' and lux_color_code = '{$Colourdata['color_code']}'";
                            if ($this->conn->query($update_data) === TRUE) {
                                echo "Record updated successfully<br>";
                            } else {
                                echo "Error update sql query: " . $update_data . "<br>" . $this->conn->error;
                            }
                        }else{
                            $sql = "INSERT INTO  items VALUES (
                                '','{$value['product_name']}','','{$value['category_name']}','','','','',
                                '','','','','{$value['Size']}',
                                '','','','','','','','','','',
                                '','','','','',
                                '','','{$Colourdata['color_code']}','','',
                                '','','',''
                                '','','',
                                '','','','','',
                                '','','',
                                '','','',
                                '','','',
                                '{$value['product_name']}','','','','','',
                                '{$Colourdata['color_name']}','','{$value['Type']}','{$value['Gender']}','{$value['Rx-able']}','',
                                '','','','','','',
                                '','',''
                                )";
                            if ($this->conn->query($sql) === TRUE) {
                                echo "Record added successfully<br>";
                            } else {
                                echo "Error: " . $sql . "<br>" . $this->conn->error;
                            }
                        }
                    }else{
                        echo "Error getting data: " . $this->conn->error;
                    }
                }
            }

            
                    
        }
        
    }

    public function closeConnection()
    {
        $this->conn->close();
    }
}

?>

