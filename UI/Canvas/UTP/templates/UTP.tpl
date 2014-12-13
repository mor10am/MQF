<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
    <META HTTP-EQUIV="Expires" CONTENT="Fri, Jan 01 1900 00:00:00 GMT">
    <META HTTP-EQUIV="Pragma" CONTENT="no-cache">
    <META HTTP-EQUIV="Cache-Control" CONTENT="no-cache">
    <META http-equiv="Content-Type" content="text/html; charset=utf-8">
    <META http-equiv="content-language" content="en">
    <META HTTP-EQUIV="Reply-to" CONTENT="mortena@tpn.no">
    <title>{$hwnd}</title>

    {$mqx_javascripts}

    {literal}

    <link href="{/literal}{$project}{literal}/style/style.css" rel="stylesheet" type="text/css" />

    <script>

    docsessionid = {/literal}'{$sessionid}'{literal};
    docproject = {/literal}'{$project}'{literal};

    {/literal}
    </script>

</head>
<body>


<div align="center">
    <table>

        <tr>
            <td style="background-repeat: no-repeat;" background="{$project}/gfx/logo.jpg">
                <br><br>
                <div align="right">
                    TPNID: <input size="9" class="mqxInput" value="{$dialerdata->ID}">
                    Telefon: <input size="11" class="mqxInput" value="{$dialerdata->PHONE1}">
                    Prosjektkode: <input size="4" class="mqxInput" value="{$dialerdata->CAMP}">
                        {if $allowmanualapp}
                        <button id='btnSpawnManualApp' onClick='spawnManualApplication();'>Start Ny Manuell Applikasjon...</button>
                        <script>
                        {literal}
                        function spawnManualApplication() {
                            mqxCallMethod('Canvas_UTP.spawnManualApplication', []);
                        }
                        {/literal}
                        </script>
                        {/if}
                    
                </div>
            </td>
        </tr>

        <tr>
            <td>
                <textarea class="txtCustomerInfo" cols="105" rows="5" readonly>
{if $manualapp}
Du har nå startet en manuell applikasjon for {$projectcustomername}. 
Husk å lukke de manuelle applikasjonene, FØR du lukker
den originale utgående applikasjonen...
{else}
God {$time_of_day_string}, det er {$agent.name} fra {$projectcustomername} som ringer.

Snakker jeg med {$dialerdata->FIRSTNAME} {$dialerdata->LASTNAME} ?
{/if}
                </textarea>
            </td>
        </tr>

    </table>
</div>

<div align="center" id="dashboardUTP">
    <table cellspacing="16" style="margin: 0px;">

        <tr>
            <td>
            <!--<button class="mqxButtonBig" id="btnFax">Fax</button>-->
            {utpfield type='button' class="mqxButtonBig" actiontype='value-const' value='FAX' method='Canvas_UTP.terminateCall' label='Fax' confirm="Du har valgt å avslutte denne samtalen med 'Fax'" disable_domid='dashboardUTP'}
            </td>
            <td><button class="mqxButtonBig" id="btnExtendCall">Forleng&nbsp;Samtalen</button></td>
            <td><button class="mqxButtonBig" id="btnCallback">Ring&nbsp;Tilbake</button></td>
            <td><button class="mqxButtonBig" id="btnSoonToBeHung">Skal&nbsp;snart&nbsp;legge&nbsp;p&aring;</button></td>
        </tr>
        <tr>
            <td>
            <!--<button class="mqxButtonBig" id="btnAnswerMachine">Telefonsvarer</button>-->
            {utpfield type='button' class="mqxButtonBig" actiontype='value-const' value='ANSWERMACHINE' id='btnAnswerMachine' method='Canvas_UTP.terminateCall' label='Telefonsvarer' confirm="Du har valgt å avslutte denne samtalen med 'Telefonsvarer'" disable_domid='dashboardUTP'}

            </td>
            <td><button class="mqxButtonBig" style="display:none;">Manuelle&nbsp;Salg</button></td>
            <td>
            {utpfield type='button' class="mqxButtonBig" actiontype='value-const' value='HAS' id='btnHas' method='Canvas_UTP.terminateCall' label='Har' confirm="Du har valgt å avslutte denne samtalen med 'Har'" disable_domid='dashboardUTP'}

            </td>
            <td><button class="mqxButtonBig" style="display:none;">Dummy</button></td>
            <!-- <td><button class="mqxButtonBig">Ring&nbsp;ut...</button></td> -->
            
        </tr>
        <tr>
            <td>
            <!--<button class="mqxButtonBig" id="btnWrongNumber">Galt&nbsp;nr</button>-->
            {utpfield type='button' class="mqxButtonBig" actiontype='value-const' value='WRONGNUMBER' id='btnWrongNumber' method='Canvas_UTP.terminateCall' label='Galt nr' confirm="Du har valgt å avslutte denne samtalen med 'Galt Nr'" disable_domid='dashboardUTP'}
            </td>
            <td><button class="mqxButtonBig" style="display:none;">Dummy</button></td>
            <td><button class="mqxButtonBig" style="display:none;">Dummy</button></td>
            
            {if $allowfollowup}
            <td><button class="mqxButtonBig" id="btnFollowUp"><font color="#aa0000">X</font>&nbsp;Manuell</button></td>
            {else}
            <td><button class="mqxButtonBig" style="display:none;">Dummy</button></td>
            {/if}
            
        </tr>
        <tr>
            <td>
            <!-- <button class="mqxButtonBig" id="btnNoAnswer">Ikke&nbsp;svar/tilgj.</button>-->
            {utpfield type='button' class="mqxButtonBig" actiontype='value-const' value='NOANSWER' id='btnNoAnswer' method='Canvas_UTP.terminateCall' label='Ikke svar/Tilgj.' confirm="Du har valgt å avslutte denne samtalen med 'Ikke svar/Tilgj.'" disable_domid='dashboardUTP'}
            </td>
            <td><button class="mqxButtonBig" id="btnYes"><font color="#00aa00">V</font>&nbsp;Ja</button></td>
            <td>
            <!--<button class="mqxButtonBig" id="btnDead">D&oslash;d/Opph&oslash;rt</button>-->
            {utpfield type='button' class="mqxButtonBig" actiontype='value-const' value='DEAD' id='btnDead' method='Canvas_UTP.terminateCall' label='Død/Opphørt' confirm="Du har valgt å avslutte denne samtalen med 'Død/opphørt'" disable_domid='dashboardUTP'}
            
            </td>
            <td><button class="mqxButtonBig" id="btnNo"><font color="#aa0000">X</font>&nbsp;Nei</button></td>
        </tr>

    </table>
</div>

<div id='{$module_project_id}'>{$module_project_html}</div>
{literal}
<script>

    // global
    Event.observe('btnNo', 'click', function(e) { showpopup('nei_panel'); } );
    
    Event.observe('btnYes', 'click', function(e) { projectOpenYesPanel(docproject); } );
    Event.observe('close_ja_panel', 'click', function(e) { projectCloseYesPanel(docproject); } );
    
    //Event.observe('btnHas', 'click', function(e) {  if(confirm("Du har valgt å avslutte denne samtalen med 'Har'")) {mqxCallMethod('Canvas_UTP.terminateCall', new Array('HAS')); disableDashboard();}});

    
    //Event.observe('btnWrongNumber', 'click', function(e) { if(confirm("Du har valgt å avslutte denne samtalen med 'Galt nummer'")) {mqxCallMethod('Canvas_UTP.terminateCall', new Array('WRONGNUMBER')); disableDashboard();}});
    //Event.observe('btnDead', 'click', function(e) { if(confirm("Du har valgt å avslutte denne samtalen med 'Død/Opphørt'")) {mqxCallMethod('Canvas_UTP.terminateCall', new Array('DEAD')); disableDashboard();}});
    Event.observe('btnCallback', 'click', function(e) { showpopup('callback_panel'); $('inpCallbackCustFName').value = $('inpCustFName').value; $('inpCallbackCustLName').value = $('inpCustLName').value; $('inpCallbackCustPhoneNo').value = $('inpCustPhoneNo').value });
    //Event.observe('btnNoAnswer', 'click', function(e) { if(confirm("Du har valgt å avslutte denne samtalen med 'Ikke svar/tilgj'")) {mqxCallMethod('Canvas_UTP.terminateCall', new Array('NOANSWER')); disableDashboard();}});
    //Event.observe('btnAnswerMachine', 'click', function(e) { if(confirm("Du har valgt å avslutte denne samtalen med 'Telefonsvarer'")) {mqxCallMethod('Canvas_UTP.terminateCall', new Array('ANSWERMACHINE')); disableDashboard();}});
    //Event.observe('btnFax', 'click', function(e) {  if(confirm("Du har valgt å avslutte denne samtalen med 'Fax'")) {mqxCallMethod('Canvas_UTP.terminateCall', new Array('FAX')); disableDashboard();}});

    Event.observe('btnExtendCall', 'click', function(e) { baduser = false; mqxCallMethod('Canvas_UTP.extendCall', new Array('extendCall')); });
    Event.observe('btnSoonToBeHung', 'click', function(e) { mqxCallMethod('Canvas_UTP.shortCall', new Array('shortCall')); });

    Event.observe('btnCallbackBack', 'click', function(e) { $('callback_panel').hide(); } );
    Event.observe('btnCallbackFinal', 'click', function(e) { if ($('inpCallbackSetResult').value == '') {alert('Husk å trykk SETT TID!');} else {  $('callback_panel').hide(); mqxCallMethod('Canvas_UTP.terminateCall', new Array('CALLBACK')); disableDashboard(); }} );

    // new PeriodicalExecuter(function(pe) { if (baduser) alert("Du må huske å bruke 'Forleng Samtalen'!"); }, 180);


    Event.observe('btnCallbackSet', 'click', function(e) { callbackSet(docproject); });

</script>
{/literal}

{if $allowfollowup}
	{literal}
	<script>
		Event.observe('btnFollowUp', 'click', function(e) { showpopup('followup_panel'); });
	</script>
	{/literal}
{/if}



</body>

</html>
