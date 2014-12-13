var baduser = true;

function projectCloseYesPanel(project)
{
    $('ja_panel').hide();
    mqxCallMethod('Module_' + project + '.hidePanel', new Array('show_yes_panel'));
}

function projectOpenYesPanel(project)
{
    mqxCallMethod('Module_' + project + '.showPanel', new Array('show_yes_panel'));
    $('nei_panel').hide();
    showpopup('ja_panel');
}

function disableDashboard()
{
    $$('#dashboardUTP .mqxButtonBig').each( function(e) { e.disabled = true; } );
}

function callbackSet(project)
{
    var h = document.getElementsByName('Time_Hour')[0].value;
    var mn = document.getElementsByName('Time_Minute')[0].value;

    var cbtime = $('calendar47_set_callback').value + ' ' + h + ':' + mn + ":00";

    mqxCallMethod('Module_' + project + '.checkValidCallbackTime', [cbtime], 'function setCallbackTimeCallback');
}

function setCallbackTimeCallback(ret)
{
    if (ret.STATUS == 'OK') {

        $('inpCallbackSetResult').value = ret.DATE_F;

        // $('btnCallback').disabled = false;

    } else {
        alert(ret.MESSAGE);
    }

}

function followupSet(project)
{

    var h = document.getElementsByName('Followup_Time_Hour')[0].value;
    var mn = document.getElementsByName('Followup_Time_Minute')[0].value;

    var cbtime = $('calendar47_set_followup').value + ' ' + h + ':' + mn  + ":00";

    mqxCallMethod('Module_' + project + '.checkValidFollowUpTime', [cbtime, $('inpFollowupComment').value], 'function setFollowupTimeCallback');
}

function setFollowupTimeCallback(ret)
{
    if (ret.STATUS == 'OK') {

        $('inpFollowupCallbackSetResult').value = ret.DATE_F;

        // $('btnCallback').disabled = false;

    } else {
        alert(ret.MESSAGE);
    }

}
