<?

class DogTools_Module extends Core_ModuleBase {

	protected function createModuleInfo() {

		return new Core_ModuleInfo(
			"Dogtools",
			"Small utilities for the Lemonstand platform.",
			"Dog in the hat"
		);
	}
	
	public function subscribeEvents() {
		Backend::$events->addEvent('cms:onRegisterTwigExtension', $this, 'on_register_twig_extension');
	}

	public function on_register_twig_extension($twig) {
	
		// Enable debug mode for Twig engine (activates the dump function - http://twig.sensiolabs.org/doc/functions/dump.html)
		$twig->enableDebug();
		$twig->addExtension(new Twig_Extension_Debug());
	}
	

}
