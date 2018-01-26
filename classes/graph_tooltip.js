/**
 * Created by Nigel.Daley on 10/07/2015.
 */


M.reportdash_graph_image_map = {



    cX	        :       0,
    cY		    :       0,
    LastcX		:       null,
    LastcY		:       null,
    rX		    :       0,
    rY		    :       0,
    currentStatus   :   false,
    initialized		:   false,
    currentTooltipDivID	: "",
    currentTitle		:"",
    currentMessage		:"",
    currentAlpha		: 0,
    timerID			    : null,
    timerInterval		: 10,
    timerStep			:5,
    currentTimerMode    :0,
    timerLock			:false,
    SmoothMove			:false,
    SmoothMoveFactor	:5,
    imageMapRandomSeed	:true,
    delimiter			:String.fromCharCode(1),
    titleTooltipMask	:false,
    descTooltipMask	    :false,



    init: function (Y,PictureID,ImageMapID,ImageMapURL,titleTooltipMask,descTooltipMask,displayTooltipText) {
        Y = Y;

        Y.one(document).on('mousemove', function(e) {
            M.reportdash_graph_image_map.UpdateCursorPosition(e);
        });

        M.reportdash_graph_image_map.titleTooltipMask   =   titleTooltipMask;
        M.reportdash_graph_image_map.descTooltipMask    =   descTooltipMask;

        M.reportdash_graph_image_map.addImage(PictureID,ImageMapID,ImageMapURL);

    },




    addImage :function(PictureID,ImageMapID,ImageMapURL)    {

        M.reportdash_graph_image_map.createTooltipDiv('testDiv');
        M.reportdash_graph_image_map.createMap(ImageMapID);
        M.reportdash_graph_image_map.bindMap(PictureID,ImageMapID);
        setTimeout("M.reportdash_graph_image_map.checkLoadingStatus('"+PictureID+"','"+ImageMapID+"','"+ImageMapURL+"')", 200);

    },

    checkLoadingStatus: function(PictureID,ImageMapID,ImageMapURL)  {
        var elecomplete = Y.one('#'+PictureID).get('complete');

        if (elecomplete == true)    {
            this.downloadImageMap(PictureID,ImageMapID,ImageMapURL);
        }   else {
            setTimeout("M.reportdash_graph_image_map.checkLoadingStatus('"+PictureID+"','"+ImageMapID+"','"+ImageMapURL+"')", 200);
        }
    },

    downloadImageMap: function(PictureID,ImageMapID,ImageMapURL)    {

        randomSeed = "Seed=" + Math.floor(Math.random()*1000);

        /*
        if ( ImageMapURL.indexOf("?",0) != -1 ) {
            ImageMapURL = ImageMapURL + "&" + randomSeed;
        } else {
            ImageMapURL = ImageMapURL + "?" + randomSeed;
        }
*/

        Y.io(ImageMapURL,{
            data : {
                'Seed':Math.floor(Math.random()*1000)
            },
            method: 'GET',

            on : {
                success: function (e,o) {
                    M.reportdash_graph_image_map.parseZones(ImageMapID,o.responseText);
                }
            }
        });
    },


    parseZones: function(ImageMapID,SerializedZones)    {
        var Zones = SerializedZones.split("\r\n");

        for(i=0;i<=Zones.length-2;i++) {
            var Options = Zones[i].split(M.reportdash_graph_image_map.delimiter);


            M.reportdash_graph_image_map.addArea(ImageMapID,Options[0],Options[1],Options[2],Options[3],Options[4].replace('"',''));


        }
    },


    /* Fade general functions */
    fadeIn: function(TooltipDivID)  {
        M.reportdash_graph_image_map.currentTimerMode = 1;
        M.reportdash_graph_image_map.initialiseTimer(TooltipDivID);
    },

    fadeOut: function(TooltipDivID) {
        M.reportdash_graph_image_map.currentTimerMode = 2;
        M.reportdash_graph_image_map.initialiseTimer(TooltipDivID);
    },

    initialiseTimer: function(TooltipDivID)  {
        if ( M.reportdash_graph_image_map.timerID == null ) {
            M.reportdash_graph_image_map.timerID = setInterval("M.reportdash_graph_image_map.fade('"+TooltipDivID+"')",M.reportdash_graph_image_map.timerInterval);
        }
    },

    fade: function(TooltipDivID) {

        var element = document.getElementById(TooltipDivID);

        M.reportdash_graph_image_map.currentStatus = true;
        if ( M.reportdash_graph_image_map.currentTimerMode == 1 ) /* Fade in */
        {
            M.reportdash_graph_image_map.currentAlpha = M.reportdash_graph_image_map.currentAlpha + M.reportdash_graph_image_map.timerStep;
            if ( M.reportdash_graph_image_map.currentAlpha >= 100 ) {
                M.reportdash_graph_image_map.currentAlpha = 100;
                clearInterval(M.reportdash_graph_image_map.timerID);
                M.reportdash_graph_image_map.timerID = null;
            }
        }
        else if ( M.reportdash_graph_image_map.currentTimerMode == 2 ) /* Fade out */
        {
            M.reportdash_graph_image_map.currentAlpha = M.reportdash_graph_image_map.currentAlpha - M.reportdash_graph_image_map.timerStep;
            if ( M.reportdash_graph_image_map.currentAlpha <= 0 ) {
                M.reportdash_graph_image_map.currentStatus = false;
                M.reportdash_graph_image_map.currentAlpha = 0;
                clearInterval(M.reportdash_graph_image_map.timerID);
                M.reportdash_graph_image_map.timerID = null;
            }
        }

        element.style.opacity = M.reportdash_graph_image_map.currentAlpha * .01;
        element.style.filter = 'alpha(opacity=' +M.reportdash_graph_image_map.currentAlpha + ')';
    },






    UpdateCursorPosition: function(e) {
        console.log('mousemove');
        M.reportdash_graph_image_map.cX = e.pageX;
        M.reportdash_graph_image_map.cY = e.pageY;

        if ( M.reportdash_graph_image_map.currentStatus  ) {
            console.log('movediv');

            M.reportdash_graph_image_map.moveDiv(M.reportdash_graph_image_map.currentTooltipDivID);

        }
    },

    addArea: function(imageMapID,shapeType,coordsList,Colour,Title,Message) {
        var maps    = document.getElementById(imageMapID);
        var element = document.createElement("AREA");

        element.shape  = shapeType;
        element.coords = coordsList;

        Y.one(element).on("mouseover",function ()   {
            M.reportdash_graph_image_map.showDiv('testDiv',Colour,Title,Message);
        });

        Y.one(element).on("mouseout",function ()   {
            M.reportdash_graph_image_map.hideDiv('testDiv');
        });

        maps.appendChild(element);


        console.log("addArea");



    },

    bindMap: function(imageID,imageMapID)   {
        image   = Y.one("#"+imageID);
        image.set('useMap',"#"+imageMapID);
    },

    createToolTip: function(TooltipDivID,Color,Title,Message)   {

        Title =     M.reportdash_graph_image_map.titleTooltipMask.replace('%s',Title);
        Message =     M.reportdash_graph_image_map.descTooltipMask.replace('%s',Message);


        var HTML = "<div style='border:2px solid #606060'><div style='background-color: #000000; font-family: tahoma; font-size: 11px; color: #ffffff; padding: 4px;'><b>"+Title+" &nbsp;</b></div>";
        HTML    += "<div style='background-color: #808080; border-top: 2px solid #606060; font-family: tahoma; font-size: 10px; color: #ffffff; padding: 2px;'>";
        HTML    += "<table style='border: 0px; padding: 0px; margin: 0px;'><tr valign='top'><td style='padding-top: 4px;'><table style='background-color: "+Color+"; border: 1px solid #000000; width: 9px; height: 9px;  padding: 0px; margin: 0px; margin-right: 2px;'><tr><td></td></tr></table></td><td>"+Message+"</td></tr></table>";
        HTML    += "</div></div>";
        Y.one("#"+TooltipDivID).set('innerHTML',HTML);
    },

    moveDiv: function(TooltipDivID)     {
        console.log("moveDiv");
        var element = document.getElementById(TooltipDivID);


        if(self.pageYOffset)    {

            console.log("pageXOffset");

            rX = self.pageXOffset;
            rY = self.pageYOffset;
        }   else if(document.documentElement && document.documentElement.scrollTop)       {

            console.log("documentElement");

            rX = document.documentElement.scrollLeft;
            rY = document.documentElement.scrollTop;
        }   else if(document.body)       {
            console.log("document.body");


            rX = document.body.scrollLeft;
            rY = document.body.scrollTop;
        }

        console.log("rX"+rX);
        console.log("rY"+rY);

        if(document.all)    {
            M.reportdash_graph_image_map.cX += rX; M.reportdash_graph_image_map.cY += rY;
        }

        console.log("cX"+M.reportdash_graph_image_map.cX);
        console.log("cY"+M.reportdash_graph_image_map.cY);

        if ( M.reportdash_graph_image_map.SmoothMove && M.reportdash_graph_image_map.LastcX != null ) {
            console.log("smooth move");
            M.reportdash_graph_image_map.cX = M.reportdash_graph_image_map.LastcX - (M.reportdash_graph_image_map.LastcX-M.reportdash_graph_image_map.cX)/4;
            M.reportdash_graph_image_map.cY = M.reportdash_graph_image_map.LastcY - (M.reportdash_graph_image_map.LastcY-M.reportdash_graph_image_map.cY)/M.reportdash_graph_image_map.SmoothMoveFactor;
        }

        console.log("cX"+M.reportdash_graph_image_map.cX);
        console.log("cY"+M.reportdash_graph_image_map.cY);

        //element.style.left    = (M.reportdash_graph_image_map.cX+10) + "px";
        Y.one('#testDiv').setStyle('left',(M.reportdash_graph_image_map.cX+10) + "px");
        Y.one('#testDiv').setStyle('top',(M.reportdash_graph_image_map.cY+10) + "px");


        //element.style.top     = (M.reportdash_graph_image_map.cY+10) + "px";


        M.reportdash_graph_image_map.LastcX = M.reportdash_graph_image_map.cX;
        M.reportdash_graph_image_map.LastcY = M.reportdash_graph_image_map.cY;

    },

    showDiv: function(TooltipDivID,Color,Title,Message) {
        console.log("showDiv");


        if ( M.reportdash_graph_image_map.currentTooltipDivID != TooltipDivID || M.reportdash_graph_image_map.currentTitle != Title || M.reportdash_graph_image_map.currentMessage != Message) {
            console.log("call createToolTip");
            console.log(TooltipDivID);
            M.reportdash_graph_image_map.createToolTip(TooltipDivID,Color,Title,Message);
        }

        if ( !M.reportdash_graph_image_map.initialized ) { M.reportdash_graph_image_map.moveDiv(TooltipDivID); M.reportdash_graph_image_map.initialized = true; }

        M.reportdash_graph_image_map.fadeIn(TooltipDivID);

        M.reportdash_graph_image_map.currentTooltipDivID	= TooltipDivID;
        M.reportdash_graph_image_map.currentTitle		= Title;
        M.reportdash_graph_image_map.currentMessage	= Message;


    },

    hideDiv: function(TooltipDivID) {
        console.log("hideDiv");
        M.reportdash_graph_image_map.fadeOut(TooltipDivID);
    },

    createTooltipDiv: function(TooltipDivID)  {
        console.log("createTooltipDiv");
        var tooltipdiv  =   Y.one('#'+TooltipDivID);
        if (tooltipdiv != 'undefined' && tooltipdiv != null)  return 0;


        ele = Y.Node.create('<div/>');
        ele.set('id',TooltipDivID);
        ele.set('innerHTML',"");
        ele.setStyle('display',"inline-block");
        ele.setStyle('position',"absolute");
        ele.setStyle('opacity',0);
        ele.setStyle('filter',"alpha(opacity=0)");

        Y.one(document.body).appendChild(ele);


    },

    createMap: function(imageMapID) {
        var imageMap  =   Y.one('#'+imageMapID);

        if (imageMapID == 'undefined' || imageMapID == null || imageMapID == 'null' )  imageMap.remove();

        ele = Y.Node.create('<map/>');
        ele.set('id',imageMapID);
        ele.set('name',imageMapID);

        Y.one(document.body).appendChild(ele);

        console.log(ele);
    }






}
