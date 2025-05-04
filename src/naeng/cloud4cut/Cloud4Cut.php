<?php

namespace naeng\cloud4cut;

use kim\present\libasynform\SimpleForm;
use naeng\CooltimeCore\CooltimeCore;
use NaengUtils\NaengUtils;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use SOFe\AwaitGenerator\Await;

class Cloud4Cut extends PluginBase{

    use SingletonTrait;

    public function onLoad() : void{
        self::setInstance($this);
    }

    public function onEnable() : void{
        NaengUtils::registerCommand("구름네컷", "나의 스킨으로 구름네컷을 촬영해보세요", "/구름네컷", DefaultPermissionNames::GROUP_USER, [], function(Player $player, array $args) : void{
            Await::f2c(function() use($player){
                $form = new SimpleForm("customUI_ServerMenuCloudForm_[BETA] 구름네컷");
                $form->setContent("나의 스킨으로 구름네컷을 촬영해보세요");
                $form->addButton("§l기본 프레임\n§r§f스폰을 배경으로 한 프레임", $form::IMAGE_TYPE_URL, "https://github.com/RICHMCBE/Cloud4Cut/blob/main/images/default-frame-low.png?raw=true");
                $form->addButton("§l준비 중\n§r§f더 멋진 프레임으로 돌아올게요", $form::IMAGE_TYPE_URL, "https://github.com/RICHMCBE/Cloud4Cut/blob/main/images/coming-soon-low.png?raw=true");

                if((yield from $form->send($player)) !== 0){
                    return;
                }

                if(!(yield from CooltimeCore::check("cloud4cut-{$player->getName()}", 43200))){
                    $player->sendMessage("§c§l§o이미지를 생성할 수 없어요! §r§7구름네컷 베타 서비스 중에는 12시간에 한 장을 생성할 수 있어요.");
                    return;
                }

                $player->sendMessage("§b§l§o이미지 생성을 시작합니다! §r§7잠시 후에 생성이 완료되면 알려드릴게요. 최대 5분이 소요될 수 있어요.");
                self::upload($player->getName(), $player->getSkin()->getSkinData());
                yield from CooltimeCore::create("cloud4cut-{$player->getName()}");
            });
        });
    }

    public static function upload(string $playerName, string $skinData) : void{
        $url = Cloud4Cut::getInstance()->getConfig()->get("url", "http://141.11.9.102:5000");

        Server::getInstance()->getAsyncPool()->submitTask(new class($playerName, $skinData, $url) extends AsyncTask{
            private readonly string $data;

            public function __construct(
                private readonly string $playerName,
                string $skinData,
                private readonly string $url
            ){
                $this->data = json_encode([
                    "playerName" => $playerName,
                    "skin" => base64_encode($skinData)
                ]);
            }

            public function onRun() : void{
                $ch = curl_init($this->url . "/make4cut");

                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 응답을 문자열로 반환
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); // JSON 형식으로 전송
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data); // JSON 데이터로 전송

                // 요청 실행 및 응답 받기
                $response = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                $this->setResult([$response, $code]);
                curl_close($ch);
            }

            public function onCompletion() : void{
                $result = $this->getResult();
                if(!is_array($result)){
                    return;
                }

                if(($result[1] ?? null) != 201){
                    return;
                }

                $player = Server::getInstance()->getPlayerExact($this->playerName);
                if($player !== null){
                    $player->sendMessage("§a§l§o사진이 도착했어요! §r§7구름서버 디스코드 -> 자랑하기 채널에서 나의 §f§l구름네컷§r§7을 확인해보세요!");
                }
            }
        });
    }


}