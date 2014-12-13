<script>
{literal}

function mqxTMCallback(ret)
{
    if (ret.LASTACTION == 'DELETE') {
        Element.remove(ret.ROWDOMID);
    } else if (ret.LASTACTION == 'UPDATE') {
        var fields = $H(ret.ORM);
        
        fields.each(function(pair) {
            var key = pair.key;
            var value = pair.value;
            
            var domid = ret.TABLE + '_' + key + '_' + ret.PK;
            
            if ($(domid)) {
                $(domid).innerHTML = value;
            }
            
        });
    } else if (ret.LASTACTION == 'ADD') {
    
    }
}

{/literal}
</script>

<table id='{$tableid}' class='mqxTMTable' cellpadding='2' cellspacing='0'>
{tmheaderrow}
{foreach item=row from=$tabledata}
    <tr id='{$table}_{tmpk activerecord=$row}' class='mqxTMRow'>        
        {tmrowcells activerecord=$row}
    </tr>
{/foreach}
</table>