ZIMRA Gnrtedf-##📋Rq#**EdpPOST ****```DvicModlNm:SevrDviceModeVrsion:v1::🔐mConfiguration### ertificate Files ocation**
- **Certificate`C:\Users\azaa\Hrd\zimra\torage\app\zira\device_cerificate.pem`
- **PrivteKey:**`C:\Users\Mazrra\Herd\zimra\sorage\app\zimra\dve_privekey`** mTLS Setup**
**→**ents2 **Adderticat**if o papha 📦Request### **mPyoi**`jn{"": 11,
  "te": "2026-02-23T08:03:09",
    "receiptCounr": 1,
  "": [        {          "ueType" "",           "fscalCouerCrny" "USD",           "fslCuterTxPecet": 0.0,
           "fisalCurTxID":513,      "fsalCountVlu":50.0    }   ],
"": "MEUCIGYtOkncGwBLKyZIZZfcO35iR8+sxRW+iMwtOAuE+G3DAiEA8uZaT/rXHk4GL76sF/d5d5kd96y5vvq7bBwZIRyUzyc="
}
``🔍Bakdown**FcalDayIfomon**- **fiscalDayNo:** 11- Th fiscal day umb bing 
- **fisclDDte:** 2026-02-230803:09` Wth was opened**reiptC:**`1` - Ttal nuberof inthsfsday
###**FscalDa Cnrs**Aggegdsales grudbytx:
|Typ|Curr | Tx ID |Tax%|Vlu||------|----------|--------|-------|-------||SleByTx | USD | 513 | 0% | $50.00|###**gntu**- **DvSgntue:**Dgtaignaturefnnictrig **Agrithm:** SHA256 with RSA-**Fm:**Ba64encde🔧Hwtheigtue s Generated**CnicatrFormt**deviceId||||||||```###**Aal for This Payload||3T08:03:09|1|||00|53|00**igurPros**
1.Bdcn string from pladfields2. Signusingdeve prive ky(SHA5withRSA)3.Base64node th sgnar4. Add to payloadas`DeviceSigaur`

---
##📝PostmanCollectionSetup
###**Step1:CreateNewRequest**
1.Clk **Nw** → **HTP Rqust**2.Name:`ZIMRACloseDay-F Day 11`
3. Methd`POT`4.URL:`https://dmpist.zim.co.zw/Dvi/v/3258/CloseDay`
###**Step2:Congure Header**
G o **Hades** tband add:
|Key|||-----|-------|
|DeviceModelName|Server||DeviceModelVersion|v1||nt-| ppiction/json ||Accept|applti/json |

### **Sp 3: onfigBody**1.Goto**Body**tab
2.Select**raw**
3. Select **JSON** rom dropdown
4. Pate the omplete pyad (see above)

### **Sp 4: Configue mLS Ctifiaes**1.Goto**Settings**(⚙️icon)→ **Certites**
2. Cick **Add tificte**
3. Fill in-**Host:**`dmpist.zim.cozw` -**Port:**`443` -**CRTle:** Selet `dce_rtifcpem   - KEY fe:**elec `device_pivate.key`
4. Clck **Add### **tp : end Requst**Click **Send** button ✅****  somuuihere  mesgeCloseDay opeatin statd### **AftrPing**    ,    "fiscalDayClosingErrorCode": null
❌ Possible ****  iscalayProcinErr  ,    "message": "Close day is not allowed. There are mismatches between counters."
 first****  iscalayProcessingrror  fiscalDayClosingErrorCode": 1,
    "Close day  not allowed. There are mis rin d (Grey vadatio e).ry validation error**ReceiptsWith**  cDyPrcessig  ,    "message": "Close day is nt alwed. Therearesdy(Rdvaidatinro).
}
```Fdatirriei🔄ReeerPyoa forDiferent F Rthgeeroip:

```bhpgne_clody_pay.pp```Edit scipto hgesca ay:```php$slDayNo=11;//ngti oyour dsfsclda```
🧪ingadd(,)Body  vid JSOwisigature
-[ ] EndoitURL correctF d"open"status (or "closed"  rery)No d/gray valdairrorinsRecept rsequeni(run repir if neded)
--

##📞ZIMRASupport

I ou nourperstenterrors:

- **Eml:**fdm@zimra.co.zw
- **Phon:** +23  758891-5**Wbsi:**htts://www.zmr.co.zw
- **Offce Hur:** Mday - Friday, 8:00 AM - 4:30 PM CAT🛠️ Troublshootig

### **CtificErr**
```
Error:unbet verify the first certificate```**Soltio:**Ensure mTLS cerifcate are correctlyfigure inPosmanStings→Cetificats

### **InvidSatur**
```
Error:InviicalDyDeviceSigature**Solution:** Regenerate ayload using`-signtumutmchcaoical str exactly
### **Fiscal Day Already Closed**Error:Fcal dys aledy **Slutin**Thisi expectd if dwasred csed successfully. Check GetSttus to verify. 📚Relaed Fil-ayload Generator`eer_cloay_.php`**RepairCmmd:**`pp/Conso/Commad/ReirReeptCns.pp`**ServicLg:** `pp/Sevce/ZirDeviceServie.pp`
-**APS:** `Fsl DevceGatwAP v7.2-cl(4).m`

---Generad:** 2026-02-26 
**DeviI 32558  **D:** 11
**Enviromn:**Tst(dmet.zmr..zw)