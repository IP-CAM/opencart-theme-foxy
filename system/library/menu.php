<?php
class Menu {
    
    static private 
            $code = 0,
            $template = "",
            $wrapper = "",
            $currentLanguageId = 0;
    
    
    /*
     * Get menu items by code(identifer)
     * 
     * ! Initialization function
     * ! USAGE
     * ! Menu::call('mainmenu');
     */
    static public function call($menu_code)
    {
        self::$code = $menu_code;
        self::$template = self::getTemplate();
        self::$wrapper = self::getWrapper();
        self::$currentLanguageId = self::getCurrentLanguageId();

        
        if (self::check($menu_code))
        {
            $result = self::query("SELECT * FROM `menu_items_lang` 
                                    JOIN `menu_items` ON (`menu_items_lang`.`menu_item_id` = `menu_items`.`id`)
                                    WHERE `menu_items`.`code` = '" . $menu_code . "' AND `menu_items_lang`.`language_id` = '" . self::$currentLanguageId . "'
                                    ORDER BY `menu_items`.`sort_order`")->rows;


            // echo print_r($result);
            echo self::renderMenu($result);
        }
        else
        {
            print("Menu '<b>" . self::$code . "</b>' doesn't exist!");
        }
    }

    /*
     * Get menu name by code(identifer)
     * 
     * ! Initialization function
     * ! USAGE
     * ! Menu::getMenuName('mainmenu');
     */
    static public function getMenuName($menu_code)
    {
        self::$code = $menu_code;
        
        if (self::check($menu_code))
        {
            $result = self::query("SELECT `name` FROM `menu` 
                                    WHERE `code` = '" . $menu_code . "'")->row;

            echo $result['name'];
        }
        else
        {
            print("Menu '<b>" . self::$code . "</b>' doesn't exist!");
        }
    }
    
    
    /*
     * Wraps menu with template_wrapper
     */
    static private function renderMenu($result)
    {
        $wrapper = self::$wrapper['template_wrapper'];
        $html = self::buildMenu($result);
        
        // Wrap result with template wrapper
        $result = str_replace('{{content}}', $html, $wrapper);
        
        return htmlspecialchars_decode($result);
    }
    
    
    /*
     * Builds tree menu
     */
    static private function buildMenu($rows, $parent = 0)
    {
        $result = "";
        // Replace last </li> to avoid errors
        $structure = str_replace(htmlspecialchars('</li>'), '', self::$template['template']);
        
        foreach ($rows as $row)
        {
            if ($row['parent'] == $parent)
            {
                // Replacing template values
                $r = str_replace('{{id}}', $row['id'], $structure);
                $r = str_replace('{{name}}', $row['name'], $r);
                $r = str_replace('{{href}}', $row['href'], $r);
                $r = str_replace('{{params}}', $row['params'], $r);
                $r = str_replace('{{self_class}}', $row['self_class'], $r);
                $r = str_replace('{{title}}', $row['title'], $r);
                $r = str_replace('{{target}}', $row['target'] ? '_blank' : '_self', $r);

                if ($row['image'])
                {
                    $r = str_replace('{{image}}', "<img style='float: left;' src='/image/" . $row['image'] . "'>", $r);
                }
                else
                {
                    $r = str_replace('{{image}}', "", $r);
                }

                // Get active href + params
                if ($row['href'] . $row['params'] == $_SERVER["REQUEST_URI"])
                // if (preg_match('/^' . preg_quote($row['href'] . $row['params'], '/') . '/i', $_SERVER["REQUEST_URI"]))
                {
                    $r = str_replace('{{active}}', 'active', $r);
                }
                // Get active ONLY href
                elseif ($row['href'] == $_SERVER["REQUEST_URI"])
                // elseif (preg_match('/^' . preg_quote($row['href'], '/') . '/i', $_SERVER["REQUEST_URI"]))
                {
                    $r = str_replace('{{active}}', 'active', $r);
                }
                // If page is news ( if in page exists '/news/' --> this page is news page :) )
                elseif (preg_match('/news/i', $row['href']) AND preg_match('/^' . preg_quote($row['href'], '/') . '/i', $_SERVER["REQUEST_URI"]))
                {
                    $r = str_replace('{{active}}', 'active', $r);
                }
                // Remove {{active}} label
                else
                {
                    $r = str_replace('{{active}}', '', $r);
                }
                
                $result .= $r;
                
                if (self::menuItemHasChildren($rows, $row['id']))
                {
                    $result .= "<div><ul>";
                    $result .= self::buildMenu($rows, $row['id']);
                    $result .= "</ul></div>";
                }
                
                $result .= "</li>";
            }
        }
        
        return $result;
    }

    
    /*
     * Checks if menu item has children
     */
    static private function menuItemHasChildren($rows, $id)
    {
        foreach ($rows as $row)
        {
            if ($row['parent'] == $id)
                return true;
        }
        
        return false;
    }
    

    /*
     * Checks for existing menu code(identifer)
     */
    static public function check($menu_code)
    {
        $query = "SELECT `code` FROM `menu` WHERE `code` = '" . $menu_code . "'";
        
        $result = self::query($query)->row;
        
        if (empty($result))
            return false;
        else
            return true;
    }


    /*
     * Gets current catalog language_id by lang_code OR by default admin values
     */
    static private function getLanguageId($lang_code = NULL)
    {
        if ( ! isset($lang_code))
        {
            $result = self::query("SELECT `value` FROM `setting` WHERE `key` = 'config_language' LIMIT 1")->row;
            return $result['value'];
        }
        else
        {
            $result = self::query("SELECT `language_id` FROM `language` WHERE `code` = '" . mysql_real_escape_string($lang_code) . "' LIMIT 1")->row;
            return $result['language_id'];
        }
    }


    /*
     * Gets current catalog language OR $_cookies lang
     */
    static private function getCurrentLanguageId()
    {
        $lang_id = isset($_SESSION['language']) ? self::getLanguageId($_SESSION['language']) : self::getLanguageId();

        return (int) $lang_id;
    }

    
    /*
     * Gets menu template to render
     */
    static private function getTemplate()
    {
        return self::query("SELECT `template` FROM `menu` WHERE `code` = '" . self::$code . "'")->row;
    }
    
    
    /*
     * Gets menu wrapper to render
     */
    static private function getWrapper()
    {
        return self::query("SELECT `template_wrapper` FROM `menu` WHERE `code` = '" . self::$code . "'")->row;
    }
    
    
    /*
     * Query a string
     */
    static private function query($sql) {
        $resource = mysql_query($sql);

        if ($resource)
        {
            if (is_resource($resource)) 
            {
                $i = 0;

                $data = array();

                while ($result = mysql_fetch_assoc($resource)) {
                        $data[$i] = $result;

                        $i++;
                }

                mysql_free_result($resource);

                $query = new stdClass();
                $query->row = isset($data[0]) ? $data[0] : array();
                $query->rows = $data;
                $query->num_rows = $i;

                unset($data);

                return $query;  
            }
            else 
            {
                return true;
            }
        }
        else
        {
            echo 'Error!';
            exit();
        }
    }
    
}
?>