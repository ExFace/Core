<?php
namespace exface\Core\DataConnectors;

/**
 * Data Connector to perform MDX queries on Microsoft Analytics Services via TSQL. 
 * 
 * This connector uses a linked server for the respective OLAP cube, which it creates
 * when connecting and drops when disconnecting.
 *
 * @author Andrej Kabachnik
 *        
 */
class MsSqlMdxConnector extends MsSqlConnector
{
    private $olapSrvproduct = '';
    
    private $olapDatasrc = '';
    
    private $olapCatalog = '';
    
    private $olapProvider = 'MSOLAP';

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        parent::performConnect();
        $initLinkedServer = <<<SQL

EXEC sp_addlinkedserver 
    @server='EXFOLAP', 
    @srvproduct='{$this->getOlapSrvproduct()}',
    @provider='{$this->getOlapProvider()}', 
    @datasrc='{$this->getOlapDatasrc()}', 
    @catalog='{$this->getOlapCatalog()}'

SQL;
        $this->runSql($initLinkedServer);

    }
    
    protected function performDisconnect()
    {
        $this->runSql("EXEC sp_dropserver @server='ASDF'");
        parent::performDisconnect();
    }

    /**
     * 
     * @return string
     */
    public function getOlapSrvproduct() : string
    {
        return $this->olapSrvproduct;
    }

    /**
     * 
     * @param string $olapSrvproduct
     * @return MsSqlMdxConnector
     */
    public function setOlapSrvproduct(string $olapSrvproduct) : MsSqlMdxConnector
    {
        $this->olapSrvproduct = $olapSrvproduct;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getOlapDatasrc() : string
    {
        return $this->olapDatasrc;
    }

    /**
     * 
     * @param string $olapDatasrc
     * @return MsSqlMdxConnector
     */
    public function setOlapDatasrc(string $olapDatasrc) : MsSqlMdxConnector
    {
        $this->olapDatasrc = $olapDatasrc;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getOlapCatalog() : string
    {
        return $this->olapCatalog;
    }

    /**
     * 
     * @param string $olapCatalog
     * @return MsSqlMdxConnector
     */
    public function setOlapCatalog(string $olapCatalog) : MsSqlMdxConnector
    {
        $this->olapCatalog = $olapCatalog;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getOlapProvider() : string
    {
        return $this->olapProvider;
    }

    /**
     * 
     * @param string $olapProvider
     * @return MsSqlMdxConnector
     */
    public function setOlapProvider(string $olapProvider) : MsSqlMdxConnector
    {
        $this->olapProvider = $olapProvider;
        return $this;
    }

}
?>