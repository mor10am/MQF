<?php

/**
* Cache
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: Cache.php 1033 2008-04-08 18:24:15Z mortena $
*
*/
final class MQF_Cache
{
    protected $cache;     ///< Zend_Cache instance

    protected $frontendoptions = array();

    protected $backendoptions = array();

    /**
    * \brief Create a new Cache
    */
    public function __construct($ttl = 60)
    {
        $this->frontendoptions['lifeTime'] = $ttl;
        $this->cache = Zend_Cache::factory('Core',
                                            'File',
                                            $this->frontendoptions,
                                            $this->backendoptions);
    }

    /**
    * \brief Generates new cachekey from supplied argument list
    *
    * \return string key
    */
    public function genKey()
    {
        $tmp = func_get_args();
        $key = md5(serialize($tmp));

        return $key;
    }

    /**
    * \brief Returns cached data for supplied id, else false
    *
    * \param string Id
    * \return mixed
    */
    public function get($id)
    {
        if ($this->cache instanceof Zend_Cache_Core) {
            $dir  = getcwd();
            $data = $this->cache->load($id);
            chdir($dir);

            if (!is_string($data)) {
                return $data;
            }

            if (!$ret = unserialize($data)) {
                return $data;
            } else {
                return $ret;
            }
        } else {
            MQF_Log::log('Trying to call getCached without Zend_Cache_Core instantiated.', MQF_WARNING);

            return false;
        }
    }

    /**
    * \brief Test cache. Used as a debug func. test is called during get anyways...
    *
    * \param string id
    * \return bool
    */
    public function getModifiedTime($id)
    {
        return $this->cache->test($id);
    }

    /**
    *\brief Adds data to cache
    *
    * \param string id
    * \param mixed data
    */
    public function save($id, $data)
    {
        if ($this->cache instanceof Zend_Cache_Core) {
            $dir = getcwd();
            $this->cache->save(serialize($data), $id);
            chdir($dir);

            MQF_Log::log('Cache with '.$id.' added to cacherepository.');

            return true;
        } else {
            MQF_Log::log('Trying to call "save" without Zend_Cache_Core instantiated.', MQF_WARNING);

            return true;
        }
    }

    /**
    * \brief Force cachettl to new.
    *
    * \param int seconds
    */
    public function setCacheTTL($ttl)
    {
        $this->cache->setLifeTime($ttl);

        return true;
    }
}
